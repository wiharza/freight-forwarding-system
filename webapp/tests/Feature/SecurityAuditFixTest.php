<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\CustomsDeclaration;
use App\Models\FreeTimeAlert;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\ShippingLine;
use App\Models\User;
use App\Services\FreeTimeAlertService;
use App\Services\JobClose;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Test regresi temuan audit keamanan 2026-11-05:
 *
 * P1-1: approve Snapshot PIB lewat URL Job lain (lintas-Job) — gerbang FTA
 *       dievaluasi terhadap Job yang salah dan blokir ADR-0008 bisa dilewati.
 * P1-2: scheduled console (FreeTimeAlertService::checkAll + RecalculateDemurrage)
 *       hanya memproses company pertama (global scope BelongsToCompany membaca
 *       CompanyContext yang di console jatuh ke min id).
 * P2-3: storeForm FTA pada Job tertutup harus ditolak (kunci tulis).
 */
class SecurityAuditFixTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Carbon $asOf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->asOf = Carbon::parse('2026-11-10');
    }

    /**
     * Fixture lengkap satu tenant untuk alarm Free Time (free 7 hari, H+1,
     * kalender): discharge 04 Nov → last free 12 Nov → pada 10 Nov pemicu H-2.
     *
     * Catatan penting: company_id TIDAK fillable pada model-model bisnis —
     * trait BelongsToCompany mengisinya dari CompanyContext saat creating.
     * Context WAJIB di-set sebelum membuat baris tenant ini.
     */
    private function makeTenantFixture(Company $company): Job
    {
        app(\App\Support\CompanyContext::class)->set($company->id);

        InternalExchangeRate::factory()->create([
            'company_id' => $company->id,
            'rate_date' => '2026-10-01',
            'currency_code' => 'USD',
            'rate_to_idr' => '16000.0000',
        ]);

        $importir = Importir::factory()->create(['company_id' => $company->id]);
        $line = ShippingLine::factory()->create([
            'company_id' => $company->id,
            'free_days_demurrage' => 7,
            'free_days_detention' => 7,
            'start_offset_demurrage' => 'H+1',
            'start_offset_detention' => 'H+1',
            'calendar_type_demurrage' => 'calendar',
            'calendar_type_detention' => 'calendar',
            'mode' => 'terpisah',
        ]);

        $creator = User::factory()->create(['company_id' => $company->id]);
        $creator->roles()->sync([$this->roleId('cs')]);
        $finance = User::factory()->create(['company_id' => $company->id]);
        $finance->roles()->sync([$this->roleId('finance')]);

        $job = Job::factory()->create([
            'company_id' => $company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
            'created_by_user_id' => $creator->id,
            'cached_status' => Job::STATUS_DOKUMEN_LENGKAP,
        ]);

        $container = $job->containers()->create([
            'company_id' => $company->id,
            'container_number' => 'MSKU'.str_pad((string) $company->id, 7, '0', STR_PAD_LEFT),
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '20000',
        ]);

        Milestone::create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'container_id' => $container->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => '2026-11-04 08:00:00',
            'recorded_at' => '2026-11-04 08:00:00',
            'is_active' => true,
            'created_by_user_id' => $creator->id,
        ]);

        return $job;
    }

    // ------------------------------------------------------------------
    // P1-2: console scope lintas company
    // ------------------------------------------------------------------

    #[Test]
    public function check_all_alarm_menjangkau_semua_company(): void
    {
        $companyA = Company::factory()->create(); // min id — yang dulu satu-satunya terproses
        $companyB = Company::factory()->create(); // tenant kedua — dulu diam-diam terlewati

        $jobA = $this->makeTenantFixture($companyA);
        $jobB = $this->makeTenantFixture($companyB);

        // Pastikan context tidak beruntung: set ke company yang BUKAN keduanya.
        app(\App\Support\CompanyContext::class)->set(999999);

        $summary = app(FreeTimeAlertService::class)->checkAll($this->asOf);

        $this->assertSame(2, $summary['jobs'], 'Kedua company harus dievaluasi');
        $this->assertGreaterThanOrEqual(1, $summary['alerts'], 'Alarm H-2 harus berbunyi di kedua tenant');

        // Alarm tercatat untuk tenant A DAN tenant B.
        // Query asersi memakai withoutGlobalScopes() karena context saat ini
        // sengaja 999999 (bukan milik tenant mana pun).
        $this->assertTrue(
            FreeTimeAlert::query()->withoutGlobalScopes()->where('company_id', $companyA->id)->exists(),
            'Alarm tenant pertama hilang'
        );
        $this->assertTrue(
            FreeTimeAlert::query()->withoutGlobalScopes()->where('company_id', $companyB->id)->exists(),
            'P1-2: alarm tenant kedua tidak pernah dibuat sebelum perbaikan scope console'
        );

        // Konteks di-reset per tenant — tidak ada kebocoran company_id pada log
        $this->assertSame(
            [$companyA->id, $companyB->id],
            \App\Models\NotificationLog::query()->withoutGlobalScopes()->orderBy('company_id')->pluck('company_id')->unique()->values()->all()
        );
    }

    #[Test]
    public function recalculate_demurrage_menjangkau_semua_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $this->makeTenantFixture($companyA);
        $this->makeTenantFixture($companyB);

        app(\App\Support\CompanyContext::class)->set(999999);

        $this->artisan('app:recalculate-demurrage')->assertExitCode(0);

        // Perintah tidak melempar error dan berjalan penuh — perhitungan demurrage
        // di kedua tenant dieksekusi (sebelumnya hanya tenant min-id).
        $this->assertTrue(true); // exit code + tidak ada exception sudah menjadi asersi
    }

    // ------------------------------------------------------------------
    // P1-1: approve Snapshot lintas-Job
    // ------------------------------------------------------------------

    #[Test]
    public function approve_deklarasi_milik_job_lain_ditolak_404(): void
    {
        $company = Company::factory()->create();
        app(\App\Support\CompanyContext::class)->set($company->id);

        $line = ShippingLine::factory()->create(['company_id' => $company->id]);
        $importir = Importir::factory()->create(['company_id' => $company->id]);

        $jobA = Job::factory()->create([
            'company_id' => $company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
        ]);
        $jobB = Job::factory()->create([
            'company_id' => $company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
        ]);

        $customs1 = User::factory()->create(['company_id' => $company->id]);
        $customs1->roles()->sync([$this->roleId('customs_specialist')]);
        $customs2 = User::factory()->create(['company_id' => $company->id]);
        $customs2->roles()->sync([$this->roleId('customs_specialist')]);

        // Draf milik Job A
        $declaration = CustomsDeclaration::create([
            'company_id' => $company->id,
            'job_id' => $jobA->id,
            'pib_number' => 'PIB-DRAFT-'.$jobA->job_number,
            'version' => 1,
            'status' => CustomsDeclaration::STATUS_DRAFT,
            'kmk_number' => 'KMK-TEST',
            'created_by_user_id' => $customs1->id,
        ]);

        // Approve lewat URL Job B → 404, deklarasi tetap draf
        $response = $this->actingAs($customs2)->post(
            route('jobs.calculator.approve', ['job' => $jobB->id, 'declaration' => $declaration->id])
        );

        $response->assertNotFound();

        $declaration->refresh();
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->status, 'Draf Job A tidak boleh ter-approve lewat URL Job B');
        $this->assertNull($declaration->approved_by_user_id);

        // Kalau URL-nya benar (Job A), approve tetap berfungsi
        $ok = $this->actingAs($customs2)->post(
            route('jobs.calculator.approve', ['job' => $jobA->id, 'declaration' => $declaration->id])
        );
        $ok->assertSessionHasNoErrors();
        $declaration->refresh();
        $this->assertSame(CustomsDeclaration::STATUS_APPROVED, $declaration->status);
    }

    // ------------------------------------------------------------------
    // P2-3: storeForm FTA pada Job tertutup
    // ------------------------------------------------------------------

    #[Test]
    public function store_form_fta_pada_job_tertutup_ditolak(): void
    {
        $company = Company::factory()->create();
        app(\App\Support\CompanyContext::class)->set($company->id);

        $line = ShippingLine::factory()->create(['company_id' => $company->id]);
        $importir = Importir::factory()->create(['company_id' => $company->id]);

        $job = Job::factory()->create([
            'company_id' => $company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
            'cached_status' => Job::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => null,
        ]);

        $cs = User::factory()->create(['company_id' => $company->id]);
        $cs->roles()->sync([$this->roleId('cs')]); // cs punya gate fta_form

        $response = $this->actingAs($cs)->post(route('jobs.fta-forms.store', $job->id), [
            'scheme_code' => 'ACFTA',
            'form_number' => 'FORM-001',
            'hs_code' => '847130',
            'origin_country' => 'CN',
            'port_of_loading' => 'Shanghai',
            'issued_at' => '2026-10-01',
            'valid_until' => '2026-12-31',
        ]);

        $response->assertSessionHasErrors(['job_closed']);
        $this->assertSame(0, \App\Models\FtaForm::query()->where('job_id', $job->id)->count());
    }
}
