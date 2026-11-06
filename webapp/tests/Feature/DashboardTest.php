<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Location;
use App\Models\Milestone;
use App\Models\ShippingLine;
use App\Models\User;
use App\Support\Audit;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes Dashboard (§2.1 / laporan (a) Q27): data live — kartu statistik,
 * tabel Job aktif dengan hitung mundur free time, aktivitas terakhir, dan
 * RBAC Q23 (Field hanya Job yang ditugaskan + tanpa angka keuangan).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $admin;

    private User $finance;

    private User $field;

    private User $fieldLain;

    private Job $jobAman;

    private Job $jobLewat;

    private Job $jobTertutup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->admin = $this->buatUser('admin');
        $this->finance = $this->buatUser('finance');
        $this->field = $this->buatUser('field');
        $this->fieldLain = $this->buatUser('field');

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'SITC',
            'free_days_demurrage' => 7,
            'free_days_detention' => 7,
            'start_offset_demurrage' => 'H+1',
            'start_offset_detention' => 'H+1',
            'calendar_type_demurrage' => 'calendar',
            'calendar_type_detention' => 'calendar',
            'mode' => 'terpisah',
        ]);
        $pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        ContainerType::query()->create([
            'company_id' => $this->company->id,
            'size' => "20'",
            'type' => 'General Purpose',
        ]);

        // Job A: aktif, free time masih aman (discharge 2 hari lalu),
        // Problem Flag Hold, invoice belum lunas Rp600.000.
        $this->jobAman = $this->buatJob($importir, $line, $pol, $pod, 'Hold');
        $this->buatKontainerDenganDischarge($this->jobAman, 'MSKU1111111', now()->subDays(2));

        // Job B: aktif, free time sudah lewat (discharge 10 hari lalu).
        $this->jobLewat = $this->buatJob($importir, $line, $pol, $pod);
        $this->buatKontainerDenganDischarge($this->jobLewat, 'MSKU2222222', now()->subDays(10));

        // Job C: tertutup — tidak boleh muncul di statistik maupun tabel.
        $this->jobTertutup = $this->buatJob($importir, $line, $pol, $pod);
        $this->jobTertutup->update(['closed_at' => now(), 'closed_by' => $this->admin->id]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->jobAman->id,
            'importir_id' => $importir->id,
            'total_invoice' => 1000000.0000,
            'total_paid' => 400000.0000,
            'status' => Invoice::STATUS_APPROVED,
            'created_by_user_id' => $this->finance->id,
        ]);

        InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'rate_to_idr' => '16000.0000',
        ]);

        // Aktivitas terakhir: satu entri audit.
        Audit::record('job.created', $this->jobAman, null, 'Job dibuat untuk tes dashboard.');

        // Field hanya ditugaskan ke Job A.
        $this->jobAman->assignField($this->field);

        app(CompanyContext::class)->set($this->company->id);
    }

    private function buatUser(string $role): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->roles()->sync([$this->roleId($role)]);

        return $user;
    }

    private function buatJob(Importir $importir, ShippingLine $line, Location $pol, Location $pod, ?string $flag = null): Job
    {
        return Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
            'pol_id' => $pol->id,
            'pod_id' => $pod->id,
            'problem_flag' => $flag,
        ]);
    }

    private function buatKontainerDenganDischarge(Job $job, string $nomor, $dischargeAt): Container
    {
        $container = $job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => $nomor,
            'size' => "20'",
            'type' => 'General Purpose',
            'seal_number' => 'SEAL-'.$nomor,
            'gross_weight_kg' => 20000,
        ]);

        $job->milestones()->create([
            'company_id' => $this->company->id,
            'container_id' => $container->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => $dischargeAt,
            'recorded_at' => now(),
            'is_backdate' => false,
            'is_active' => true,
            'created_by_user_id' => $this->admin->id,
        ]);

        return $container;
    }

    #[Test]
    public function guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
    }

    #[Test]
    public function statistik_menghitung_job_aktif_flag_dan_free_time()
    {
        $this->actingAs($this->finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.jobs_active', 2)
                ->where('stats.jobs_with_flag', 1)
                ->where('stats.flags.Hold', 1)
                ->where('stats.free_time.overdue', 1)
                ->where('stats.free_time.ok', 1)
                ->where('stats.free_time.urgent', 0)
                ->where('stats.free_time.unavailable', 0)
                ->has('jobs.data', 2)
                ->where('jobs.total', 2)
                ->has('activity', 1)
                ->where('activity.0.action', 'job.created'));
    }

    #[Test]
    public function tabel_job_menampilkan_flag_dan_hitung_mundur_free_time()
    {
        $this->actingAs($this->finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $page->has('jobs.data', 2);
                $jobs = $page->toArray()['props']['jobs']['data'];
                $aman = collect($jobs)->firstWhere('job_number', $this->jobAman->job_number);
                $lewat = collect($jobs)->firstWhere('job_number', $this->jobLewat->job_number);

                $this->assertNotNull($aman);
                $this->assertNotNull($lewat);
                $this->assertSame('Hold', $aman['problem_flag']);
                $this->assertGreaterThan(0, $aman['free_time']['remaining_days']);
                $this->assertSame('hijau', $aman['free_time']['lampu']);
                $this->assertLessThan(0, $lewat['free_time']['remaining_days']);
                $this->assertSame('merah', $lewat['free_time']['lampu']);
                // Job tertutup tidak muncul di tabel.
                $this->assertNull(collect($jobs)->firstWhere('job_number', $this->jobTertutup->job_number));
            });
    }

    #[Test]
    public function finance_melihat_tunggakan_invoice_admin_tidak()
    {
        $this->actingAs($this->finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can_see_finance', true)
                ->where('unpaid.count', 1)
                // Float bulat di-encode JSON sebagai 600000 (bukan 600000.0).
                ->where('unpaid.total', 600000));

        // Ability laporan_keuangan hanya untuk Finance — Admin tidak
        // menerima angka keuangan sama sekali (prop null).
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can_see_finance', false)
                ->where('unpaid', null));
    }

    #[Test]
    public function field_hanya_melihat_job_yang_ditugaskan_dan_tanpa_angka_keuangan()
    {
        $this->actingAs($this->field)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.jobs_active', 1)
                ->where('can_see_finance', false)
                ->where('unpaid', null)
                ->has('jobs.data', 1)
                ->where('jobs.data.0.job_number', $this->jobAman->job_number));

        // Field yang tidak ditugaskan ke Job mana pun: dashboard kosong.
        $this->actingAs($this->fieldLain)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.jobs_active', 0)
                ->has('jobs.data', 0));
    }

    #[Test]
    public function dashboard_tetap_tampil_bila_kurs_internal_belum_diatur()
    {
        InternalExchangeRate::query()->delete();

        $this->actingAs($this->finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.free_time.unavailable', 2)
                ->where('jobs.data.0.free_time_unavailable', true));
    }
}
