<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\Job;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi temuan audit 8 item (2026-11-05), item 1 — isolasi tenant:
 *
 * (a) Middleware SetCompanyContext dipasang setelah route model binding
 *     (append di grup web) — binding Job jalan duluan dan global scope
 *     BelongsToCompany membaca context yang belum di-set.
 * (b) CompanyContext::id() jatuh ke tenant ber-ID terendah bila belum
 *     di-set — Job tenant lain bisa dibuka lewat URL.
 * (c) JobPolicy::view (dan TripPolicy::view) tidak mencocokkan company_id
 *     (Tiket 02).
 *
 * Skenario: dua company aktif. User company B (id lebih tinggi) membuka
 * Job company A (id terendah) lewat URL — harus 404, bukan 200.
 */
class Audit8ItemTenantTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $companyA;

    private Company $companyB;

    private Job $jobA;

    private User $userB;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();
        $this->assertTrue($this->companyA->id < $this->companyB->id, 'Fixture butuh company A ber-ID lebih rendah dari B.');

        app(CompanyContext::class)->set($this->companyA->id);
        $importir = Importir::factory()->create(['company_id' => $this->companyA->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->companyA->id]);
        $pol = Location::factory()->create(['company_id' => $this->companyA->id, 'type' => Location::TYPE_PORT]);
        $pod = Location::factory()->create(['company_id' => $this->companyA->id, 'type' => Location::TYPE_PORT]);

        $this->jobA = Job::factory()->create([
            'company_id' => $this->companyA->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
            'pol_id' => $pol->id,
            'pod_id' => $pod->id,
        ]);
        $this->jobA->initializeChecklist();

        $this->userB = User::factory()->create(['company_id' => $this->companyB->id]);
        $this->userB->roles()->sync([$this->roleId('ops')]);
        $this->adminB = User::factory()->create(['company_id' => $this->companyB->id]);
        $this->adminB->roles()->sync([$this->roleId('admin')]);
    }

    /**
     * Simulasi request segar: context dikosongkan dulu seperti di produksi
     * (tidak ada sisa set() dari proses lain).
     */
    private function freshRequestContext(): void
    {
        app(CompanyContext::class)->set(null);
    }

    #[Test]
    public function lintas_tenant_buka_job_lewat_url_ditolak_404(): void
    {
        $this->freshRequestContext();

        $response = $this->actingAs($this->userB)->get(route('jobs.show', $this->jobA));

        $response->assertNotFound();
    }

    #[Test]
    public function lintas_tenant_verifikasi_dokumen_ditolak_404(): void
    {
        $doc = $this->jobA->documents()->firstOrFail();
        $this->freshRequestContext();

        $response = $this->actingAs($this->userB)->post(
            route('jobs.documents.verify', [$this->jobA->id, $doc->id]),
            ['notes' => 'Coba verifikasi lintas tenant.'],
        );

        $response->assertNotFound();
        $this->assertSame('belum_ada', $doc->fresh()->status);
    }

    #[Test]
    public function lintas_tenant_halaman_buat_invoice_ditolak_404(): void
    {
        $this->freshRequestContext();
        $financeB = User::factory()->create(['company_id' => $this->companyB->id]);
        $financeB->roles()->sync([$this->roleId('finance')]);

        $response = $this->actingAs($financeB)->get(
            route('finance.invoices.create', ['job' => $this->jobA->id])
        );

        // Route create invoice memakai route model binding Job — Job tenant
        // lain tidak boleh ter-resolve.
        $response->assertNotFound();
    }

    #[Test]
    public function lintas_tenant_simpan_milestone_ditolak_404(): void
    {
        $container = $this->jobA->containers()->first();
        $this->freshRequestContext();

        $response = $this->actingAs($this->userB)->post(
            route('jobs.milestones.store', $this->jobA),
            [
                'milestone_type' => 'discharge',
                'container_id' => $container?->id,
                'occurred_at' => now()->toDateTimeString(),
            ],
        );

        $response->assertNotFound();
    }

    #[Test]
    public function satu_tenant_buka_job_sendiri_tetap_bisa(): void
    {
        $this->freshRequestContext();
        $userA = User::factory()->create(['company_id' => $this->companyA->id]);
        $userA->roles()->sync([$this->roleId('ops')]);

        $response = $this->actingAs($userA)->get(route('jobs.show', $this->jobA));

        $response->assertOk();
    }

    #[Test]
    public function policy_view_menolak_user_tenant_lain(): void
    {
        // Policy adalah pertahanan berlapis bila binding dilewati: ambil Job
        // tanpa scope, lalu periksa Gate secara langsung.
        $jobA = Job::withoutGlobalScopes()->findOrFail($this->jobA->id);

        $this->assertFalse(Gate::forUser($this->userB)->allows('view', $jobA));
        $this->assertFalse(Gate::forUser($this->adminB)->allows('view', $jobA));

        $userA = User::factory()->create(['company_id' => $this->companyA->id]);
        $userA->roles()->sync([$this->roleId('ops')]);
        $this->assertTrue(Gate::forUser($userA)->allows('view', $jobA));
    }

    #[Test]
    public function company_context_tanpa_tenant_gagal_tertutup(): void
    {
        app(CompanyContext::class)->set(null);

        $this->expectException(\RuntimeException::class);
        app(CompanyContext::class)->id();
    }

    #[Test]
    public function login_user_tenant_kedua_tetap_bisa(): void
    {
        // User dikecualikan dari global scope tenant (login harus mencari
        // di semua tenant) — user company B harus tetap bisa login.
        $this->freshRequestContext();

        $response = $this->post('/login', [
            'email' => $this->userB->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->userB);
    }
}
