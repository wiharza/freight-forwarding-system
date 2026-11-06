<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Job;
use App\Models\Location;
use App\Models\Milestone;
use App\Models\ShippingLine;
use App\Models\User;
use App\Services\DashboardFreeTime;
use App\Services\DemurrageCalculator;
use App\Support\CompanyContext;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes cache dashboard (keputusan user 2026-11-06: cache + paginasi).
 *
 * Pengukuran (deterministik, bukan wall-clock): DemurrageCalculator
 * dihitung per Job aktif di SETIAP request sebelum perbaikan — request
 * dingin pertama memanggilnya N kali untuk N Job. Setelah perbaikan,
 * request kedua dalam TTL tidak memanggilnya lagi sama sekali (0 panggilan
 * baru), dan perubahan milestone membatalkan cache Job itu saja.
 */
class CountingDemurrageCalculator extends DemurrageCalculator
{
    public static int $calls = 0;

    public function calculateForJob(Job $job, ?CarbonInterface $asOfDate = null): array
    {
        self::$calls++;

        return parent::calculateForJob($job, $asOfDate);
    }

    public static function reset(): void
    {
        self::$calls = 0;
    }
}

class DashboardCacheTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $admin;

    private User $finance;

    private Importir $importir;

    private ShippingLine $line;

    private Location $pol;

    private Location $pod;

    private Job $jobAman;

    private Job $jobLewat;

    private Container $containerAman;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->admin = $this->buatUser('admin');
        $this->finance = $this->buatUser('finance');

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $this->line = ShippingLine::factory()->create([
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
        $this->pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $this->pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        ContainerType::query()->create([
            'company_id' => $this->company->id,
            'size' => "20'",
            'type' => 'General Purpose',
        ]);

        // Job A: aktif, free time masih aman (discharge 2 hari lalu).
        $this->jobAman = $this->buatJob();
        $this->containerAman = $this->buatKontainerDenganDischarge($this->jobAman, 'MSKU1111111', now()->subDays(2));

        // Job B: aktif, free time sudah lewat (discharge 10 hari lalu).
        $this->jobLewat = $this->buatJob();
        $this->buatKontainerDenganDischarge($this->jobLewat, 'MSKU2222222', now()->subDays(10));

        InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'rate_to_idr' => '16000.0000',
        ]);

        // Kalkulator penghitung menggantikan yang asli di container.
        CountingDemurrageCalculator::reset();
        app()->bind(DemurrageCalculator::class, fn () => new CountingDemurrageCalculator);

        app(CompanyContext::class)->set($this->company->id);
    }

    private function buatUser(string $role): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->roles()->sync([$this->roleId($role)]);

        return $user;
    }

    private function buatJob(): Job
    {
        return Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'pol_id' => $this->pol->id,
            'pod_id' => $this->pod->id,
        ]);
    }

    private function buatKontainerDenganDischarge(Job $job, string $nomor, $dischargeAt): Container
    {
        $container = $job->containers()->create([
            'company_id' => $job->company_id,
            'container_number' => $nomor,
            'size' => "20'",
            'type' => 'General Purpose',
            'seal_number' => 'SEAL-'.$nomor,
            'gross_weight_kg' => 20000,
        ]);

        $job->milestones()->create([
            'company_id' => $job->company_id,
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
    public function request_kedua_dalam_ttl_tidak_menghitung_ulang_kalkulator(): void
    {
        // Request dingin: 1 panggilan kalkulator per Job aktif (2 Job).
        // Inilah yang dilakukan kode lama di SETIAP request.
        $this->actingAs($this->finance)->get('/dashboard')->assertOk();
        $this->assertSame(2, CountingDemurrageCalculator::$calls);
        $this->assertTrue(Cache::has(DashboardFreeTime::key($this->company->id, $this->jobAman->id)));
        $this->assertTrue(Cache::has(DashboardFreeTime::key($this->company->id, $this->jobLewat->id)));

        // Request kedua dalam TTL: 0 panggilan baru, angka tetap sama.
        $this->actingAs($this->finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.free_time.overdue', 1)
                ->where('stats.free_time.ok', 1));
        $this->assertSame(2, CountingDemurrageCalculator::$calls);
    }

    #[Test]
    public function milestone_baru_membatalkan_cache_job_itu_saja(): void
    {
        $this->actingAs($this->finance)->get('/dashboard')->assertOk();
        $this->assertSame(2, CountingDemurrageCalculator::$calls);

        // Catat DO Terbit dulu (syarat kronologis), lalu gate-out untuk
        // kontainer Job A lewat endpoint milestone.
        $this->actingAs($this->admin)->post(route('jobs.milestones.store', $this->jobAman->id), [
            'milestone_type' => Milestone::TYPE_DO_RELEASE,
            'occurred_at' => now()->subMinutes(30)->toDateTimeString(),
            'backdate_reason' => 'DO terbit sebelum gate-out (data tes cache).',
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->admin)->post(route('jobs.milestones.store', $this->jobAman->id), [
            'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
            'occurred_at' => now()->toDateTimeString(),
            'container_id' => $this->containerAman->id,
        ]);
        $response->assertSessionHasNoErrors();

        // Cache Job A terhapus, cache Job B utuh.
        $this->assertFalse(Cache::has(DashboardFreeTime::key($this->company->id, $this->jobAman->id)));
        $this->assertTrue(Cache::has(DashboardFreeTime::key($this->company->id, $this->jobLewat->id)));

        // Request berikutnya menghitung ulang HANYA Job A (2 + 1 = 3),
        // dan hitung mundurnya mencerminkan gate-out yang baru dicatat
        // (jam detention kini berjalan — Job A tetap 1 kontainer berjalan).
        $this->actingAs($this->finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.free_time.ok', 1));
        $this->assertSame(3, CountingDemurrageCalculator::$calls);
    }

    #[Test]
    public function tabel_job_dipaginasi_server_side_15_baris(): void
    {
        // Total 16 Job aktif — melebihi satu halaman (15).
        for ($i = 0; $i < 14; $i++) {
            $this->buatJob();
        }

        $this->actingAs($this->finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Statistik tetap mencakup SELURUH Job aktif, bukan
                // hanya halaman yang tampil.
                ->where('stats.jobs_active', 16)
                ->has('jobs.data', 15)
                ->where('jobs.total', 16)
                ->where('jobs.last_page', 2)
                ->where('jobs.current_page', 1));

        $this->actingAs($this->finance)->get('/dashboard?page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('jobs.data', 1)
                ->where('jobs.current_page', 2));
    }

    #[Test]
    public function cache_tidak_bocor_antar_company(): void
    {
        $company2 = Company::factory()->create();
        $jobLain = Job::factory()->create([
            'company_id' => $company2->id,
            'importir_id' => Importir::factory()->create(['company_id' => $company2->id])->id,
            'shipping_line_id' => ShippingLine::factory()->create(['company_id' => $company2->id])->id,
            'pol_id' => Location::factory()->create(['company_id' => $company2->id, 'type' => Location::TYPE_PORT])->id,
            'pod_id' => Location::factory()->create(['company_id' => $company2->id, 'type' => Location::TYPE_PORT])->id,
        ]);
        $this->buatKontainerDenganDischarge($jobLain, 'MSKU9999999', now()->subDays(2));

        $this->actingAs($this->finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.jobs_active', 2));

        // Cache Job tenant lain tidak pernah tersentuh.
        $this->assertFalse(Cache::has(DashboardFreeTime::key($company2->id, $jobLain->id)));
    }
}
