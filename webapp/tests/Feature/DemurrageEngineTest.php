<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Job;
use App\Models\LineTariff;
use App\Models\Milestone;
use App\Models\ShippingLine;
use App\Services\DemurrageCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class DemurrageEngineTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;
    private ShippingLine $line;
    private Job $job;
    private Container $c20;
    private DemurrageCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(\App\Support\CompanyContext::class)->set($this->company->id);

        $this->calc = app(DemurrageCalculator::class);

        // 1. Buat Shipping Line dengan setting standar: 7 hari free, H+1 offset, calendar
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

        // 2. Buat Tarif Tiering 20ft untuk Demurrage:
        // Tier 1: Hari 1 s.d 3 = USD 35 / hari
        // Tier 2: Hari 4 s.d 7 = USD 65 / hari
        // Tier 3: Hari 8 ke atas = USD 110 / hari
        LineTariff::factory()->create([
            'company_id' => $this->company->id,
            'shipping_line_id' => $this->line->id,
            'charge_type' => LineTariff::TYPE_DEMURRAGE,
            'container_size' => '20',
            'day_from' => 1,
            'day_to' => 3,
            'rate_per_day' => '35.0000',
            'status' => LineTariff::STATUS_ACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        LineTariff::factory()->create([
            'company_id' => $this->company->id,
            'shipping_line_id' => $this->line->id,
            'charge_type' => LineTariff::TYPE_DEMURRAGE,
            'container_size' => '20',
            'day_from' => 4,
            'day_to' => 7,
            'rate_per_day' => '65.0000',
            'status' => LineTariff::STATUS_ACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        LineTariff::factory()->create([
            'company_id' => $this->company->id,
            'shipping_line_id' => $this->line->id,
            'charge_type' => LineTariff::TYPE_DEMURRAGE,
            'container_size' => '20',
            'day_from' => 8,
            'day_to' => null,
            'rate_per_day' => '110.0000',
            'status' => LineTariff::STATUS_ACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        // 3. Set Kurs Internal hari ini (1 USD = Rp 15.800)
        InternalExchangeRate::factory()->create([
            'company_id' => $this->company->id,
            'rate_date' => '2026-11-03',
            'currency_code' => 'USD',
            'rate_to_idr' => '15800.0000',
        ]);

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $this->line->id,
        ]);

        $this->c20 = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'SITU2026001',
            'size' => '20',
            'type' => 'Dry',
            'gross_weight_kg' => '20000',
        ]);
    }

    #[Test]
    public function golden_test_kalkulasi_demurrage_tiering_tepat_secara_aritmetika(): void
    {
        // Skenario: Bongkar kapal tanggal 1 Nov 2026
        // Start H+1 = 2 Nov 2026
        // Free Time = 7 hari (2 Nov s.d 8 Nov adalah masa gratis)
        // Gate Out dilakukan tanggal 13 Nov 2026
        // Total durasi = 2 Nov s.d 13 Nov = 12 hari
        // Hari keterlambatan = 12 - 7 = 5 HARI!
        // Perhitungan denda:
        //   Hari 1 s.d 3 = 3 hari x USD 35 = USD 105
        //   Hari 4 s.d 5 = 2 hari x USD 65 = USD 130
        //   TOTAL DENDA = USD 235.00! (Rp 3.713.000 @ 15.800)

        $this->job->milestones()->create([
            'company_id' => $this->company->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => '2026-11-01 10:00:00',
            'recorded_at' => now(),
            'is_active' => true,
        ]);

        $this->job->milestones()->create([
            'company_id' => $this->company->id,
            'container_id' => $this->c20->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
            'occurred_at' => '2026-11-13 15:00:00',
            'recorded_at' => now(),
            'is_active' => true,
        ]);

        $res = $this->calc->calculateForContainer($this->c20, Carbon::parse('2026-11-03'));

        $this->assertTrue($res['demurrage']['has_started']);
        $this->assertTrue($res['demurrage']['is_final']); // gate-out sudah ada -> demurrage final!
        $this->assertEquals(12, $res['demurrage']['total_days']);
        $this->assertEquals(5, $res['demurrage']['overdue_days']);
        $this->assertEquals(235.00, $res['demurrage']['cost_usd']);
        $this->assertEquals(3713000.00, $res['demurrage']['cost_idr']);
        $this->assertEquals('merah', $res['demurrage']['lampu']);
    }

    #[Test]
    public function anti_double_count_gate_out_tidak_dihitung_ganda_pada_demurrage_dan_detention(): void
    {
        // Discharge: 1 Nov
        // Gate-Out: 5 Nov (hari terakhir demurrage)
        // Gate-In: 10 Nov
        $this->job->milestones()->createMany([
            [
                'company_id' => $this->company->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_DISCHARGE,
                'occurred_at' => '2026-11-01 10:00:00',
                'recorded_at' => now(),
                'is_active' => true,
            ],
            [
                'company_id' => $this->company->id,
                'container_id' => $this->c20->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
                'occurred_at' => '2026-11-05 14:00:00',
                'recorded_at' => now(),
                'is_active' => true,
            ],
            [
                'company_id' => $this->company->id,
                'container_id' => $this->c20->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_GATE_IN_EMPTY,
                'occurred_at' => '2026-11-10 11:00:00',
                'recorded_at' => now(),
                'is_active' => true,
            ],
        ]);

        $res = $this->calc->calculateForContainer($this->c20, Carbon::parse('2026-11-03'));

        // Demurrage: H+1 dari 1 Nov = 2 Nov s.d 5 Nov = 4 hari (masih dalam 7 hari free -> overdue 0)
        $this->assertEquals(4, $res['demurrage']['total_days']);
        $this->assertEquals(0, $res['demurrage']['overdue_days']);

        // Detention: H+1 dari 5 Nov = 6 Nov s.d 10 Nov = 5 hari (masih dalam 7 hari free -> overdue 0)
        // Tanggal 5 Nov TIDAK dihitung dua kali!
        $this->assertEquals(5, $res['detention']['total_days']);
        $this->assertEquals(0, $res['detention']['overdue_days']);

        $this->assertTrue($res['is_all_final']);
    }

    #[Test]
    public function offset_h0_dan_working_days_mengubah_durasi_perhitungan(): void
    {
        // Ubah line menjadi offset H+0 dan kalender working days
        $this->line->update([
            'start_offset_demurrage' => 'H+0',
            'calendar_type_demurrage' => 'working',
            'free_days_demurrage' => 3,
        ]);

        // Discharge Jumat 2026-11-06
        $this->job->milestones()->create([
            'company_id' => $this->company->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_DISCHARGE,
            'occurred_at' => '2026-11-06 10:00:00', // Jumat
            'recorded_at' => now(),
            'is_active' => true,
        ]);

        // AsOf Selasa 2026-11-10 (Jumat, Sabtu, Minggu, Senin, Selasa)
        // Dengan working days: Jumat (1), Senin (2), Selasa (3) = 3 hari kerja!
        // Karena H+0, Jumat langsung dihitung.
        $res = $this->calc->calculateForContainer($this->c20, Carbon::parse('2026-11-10'));

        $this->assertEquals(3, $res['demurrage']['total_days']);
        $this->assertEquals(0, $res['demurrage']['overdue_days']); // Pas 3 hari kerja
    }

    #[Test]
    public function mode_gabungan_menggunakan_pool_free_time_bersama(): void
    {
        // 7 dem + 7 det = 14 pool hari gabungan (§4.2)
        $this->line->update([
            'mode' => 'gabungan',
        ]);

        // Discharge 1 Nov, Gate-Out 10 Nov (10 hari), Gate-In 18 Nov (8 hari berikutnya)
        // Total durasi = 2 Nov s.d 18 Nov = 17 hari
        // Pool = 14 hari
        // Overdue = 17 - 14 = 3 hari
        $this->job->milestones()->createMany([
            [
                'company_id' => $this->company->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_DISCHARGE,
                'occurred_at' => '2026-11-01 10:00:00',
                'recorded_at' => now(),
                'is_active' => true,
            ],
            [
                'company_id' => $this->company->id,
                'container_id' => $this->c20->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
                'occurred_at' => '2026-11-10 10:00:00',
                'recorded_at' => now(),
                'is_active' => true,
            ],
            [
                'company_id' => $this->company->id,
                'container_id' => $this->c20->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_GATE_IN_EMPTY,
                'occurred_at' => '2026-11-18 10:00:00',
                'recorded_at' => now(),
                'is_active' => true,
            ],
        ]);

        $res = $this->calc->calculateForContainer($this->c20, Carbon::parse('2026-11-03'));

        // 3 hari overdue di pool gabungan: 3 hari x USD 35 = USD 105.00
        $this->assertEquals(3, $res['demurrage']['overdue_days']);
        $this->assertEquals(105.00, $res['total_usd']);
    }

    #[Test]
    public function artisan_command_recalculate_demurrage_berjalan_sukses(): void
    {
        $this->artisan('app:recalculate-demurrage')
            ->assertSuccessful();
    }
}
