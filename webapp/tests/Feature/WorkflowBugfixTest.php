<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\FtaTariff;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\MfnTariff;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ShippingLine;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Services\PibCalculator;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Test regresi untuk bug yang ditemukan pada verifikasi alur kerja menyeluruh
 * di VPS 2026-11-04 (core 5/5 + money 19/19 di area yang terjangkau, tetapi
 * 3 bug KRITIS + beberapa HIGH/MEDIUM memblokir pemakaian operasional):
 *
 *  1. Penomoran invoice mentok di 2 invoice (filter_var mempertahankan '-').
 *  2. BM selalu 0 lewat UI (lookup HS digit vs tarif master bertitik).
 *  3. Entri milestone 500 untuk 4 dari 7 jenis (container_id tidak dikirim UI).
 *  4. Tombol "Alokasikan" mati (nama route salah) + Titipan Dana tidak pernah
 *     terbentuk dari kelebihan bayar (diuji di InvoicePaymentTest).
 *  5. Trip show 500 (relasi vendorInvoices) + laporan aging/demurrage/
 *     estimate-vs-actual + eksposur negatif.
 *  6. Validasi kronologis lolos bila pendahulu tidak ada.
 */
class WorkflowBugfixTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $finance;

    private User $cs;

    private User $ops;

    private User $field;

    private Importir $importir;

    private Job $job;

    private Container $c1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->field = User::factory()->create(['company_id' => $this->company->id]);
        $this->field->roles()->sync([$this->roleId('field')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);

        $this->c1 = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1234567',
            'size' => '40',
            'type' => 'High Cube',
            'gross_weight_kg' => '21500',
        ]);

        $this->job->assignField($this->field, $this->ops);
    }

    // ------------------------------------------------------------------
    // BUG 1: Penomoran invoice — 3 invoice berurutan tanpa tabrakan
    // ------------------------------------------------------------------

    #[Test]
    public function penomoran_invoice_berurutan_untuk_tiga_invoice(): void
    {
        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($this->finance)->post(route('finance.invoices.store', ['job' => $this->job->id]), [
                'invoice_date' => '2026-11-04',
                'blok_c_service_fee' => 100000,
                'blok_c_ppn_rate' => '0.12',
            ]);
            $response->assertSessionHasNoErrors();
            $numbers[] = Invoice::query()->orderByDesc('id')->firstOrFail()->invoice_number;
        }

        $year = date('Y');
        $this->assertSame(["INV-{$year}-000001", "INV-{$year}-000002", "INV-{$year}-000003"], $numbers,
            'Invoice ke-3 sebelumnya bernomor INV-2026-0-2025 dan menabrak unique constraint');
        $this->assertCount(3, array_unique($numbers), 'Nomor invoice harus unik');
    }

    // ------------------------------------------------------------------
    // BUG 2: BM mengikuti tarif master bertitik (MFN + FTA)
    // ------------------------------------------------------------------

    #[Test]
    public function bm_mengikuti_tarif_mfn_bertitik(): void
    {
        MfnTariff::create([
            'company_id' => $this->company->id,
            'hs_code' => '3926.90.99', // bertitik, seperti hasil seed LegalTariffSeeder
            'description' => 'Barang Plastik Lainnya',
            'bm_rate' => 0.10,
            'effective_from' => '2022-04-01',
            'verification_status' => MfnTariff::STATUS_VERIFIED,
        ]);

        $res = (new PibCalculator)->calculateItem([
            'hs_code' => '39269099', // digit murni, seperti hasil normalizeHsCode
            'fob_valas' => 10000,
            'freight_valas' => 0,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 0,
            'kurs_pajak_rate' => 16000,
            'currency_code' => 'USD',
        ]);

        // CIF Rp160.000.000 × 10% = Rp16.000.000 (sebelum perbaikan: 0)
        $this->assertSame('mfn', $res['bm_type']);
        $this->assertEqualsWithDelta(0.10, (float) $res['bm_rate'], 0.0001);
        $this->assertEqualsWithDelta(16000000, (float) $res['bm_amount'], 0.01);
    }

    #[Test]
    public function bm_mengikuti_tarif_fta_bertitik_dan_prefix_8_digit(): void
    {
        FtaTariff::create([
            'company_id' => $this->company->id,
            'scheme_code' => 'ACFTA',
            'hs_code' => '3926.90.99',
            'fta_rate' => 0.05,
            'coo_type' => 'Form E',
            'effective_from' => '2023-01-01',
            'effective_to' => '2027-12-31',
            'verification_status' => FtaTariff::STATUS_VERIFIED,
        ]);

        $base = [
            'fob_valas' => 10000,
            'freight_valas' => 0,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 0,
            'kurs_pajak_rate' => 16000,
            'currency_code' => 'USD',
        ];

        $res = (new PibCalculator)->calculateItem(array_merge($base, ['hs_code' => '39269099']), 'API-U', 'ACFTA');
        $this->assertSame('fta', $res['bm_type']);
        $this->assertEqualsWithDelta(0.05, (float) $res['bm_rate'], 0.0001);
        $this->assertEqualsWithDelta(8000000, (float) $res['bm_amount'], 0.01);

        // HS 10 digit (BTKI + suffix CEISA) tetap cocok ke tarif 8 digit
        $res10 = (new PibCalculator)->calculateItem(array_merge($base, ['hs_code' => '3926909900']), 'API-U', 'ACFTA');
        $this->assertEqualsWithDelta(0.05, (float) $res10['bm_rate'], 0.0001);
    }

    // ------------------------------------------------------------------
    // BUG 3: Entri milestone tanpa container_id (4 jenis level-Job)
    // ------------------------------------------------------------------

    #[Test]
    public function entri_tujuh_jenis_milestone_tanpa_container_id_untuk_level_job(): void
    {
        // Urutan sesuai rantai kronologis; 4 jenis level-Job TIDAK mengirim
        // container_id sama sekali (seperti form UI) — sebelumnya 500
        // "Undefined array key container_id".
        $steps = [
            ['type' => Milestone::TYPE_PIB_FILED, 'hour' => 1, 'container' => false, 'proof' => 'PIB-TEST-1'],
            ['type' => Milestone::TYPE_DISCHARGE, 'hour' => 2, 'container' => false, 'proof' => null],
            ['type' => Milestone::TYPE_DO_RELEASE, 'hour' => 3, 'container' => false, 'proof' => 'DO-TEST-1'],
            ['type' => Milestone::TYPE_SPPB, 'hour' => 4, 'container' => false, 'proof' => 'SPPB-TEST-1'],
            ['type' => Milestone::TYPE_GATE_OUT_FULL, 'hour' => 5, 'container' => true, 'proof' => null],
            ['type' => Milestone::TYPE_DELIVERED, 'hour' => 6, 'container' => true, 'proof' => null],
            ['type' => Milestone::TYPE_GATE_IN_EMPTY, 'hour' => 7, 'container' => true, 'proof' => null],
        ];

        foreach ($steps as $step) {
            $payload = [
                'milestone_type' => $step['type'],
                'occurred_at' => now()->addHours($step['hour'])->format('Y-m-d H:i:s'),
            ];
            if ($step['container']) {
                $payload['container_id'] = $this->c1->id;
            }
            if ($step['proof']) {
                $payload['proof_number'] = $step['proof'];
            }

            $response = $this->actingAs($this->field)->post(route('jobs.milestones.store', $this->job->id), $payload);
            $response->assertSessionHasNoErrors("Milestone {$step['type']} gagal disimpan");

            $this->assertDatabaseHas('milestones', [
                'job_id' => $this->job->id,
                'milestone_type' => $step['type'],
                'container_id' => $step['container'] ? $this->c1->id : null,
            ]);
        }

        $this->assertEquals(Job::STATUS_GATE_IN_SELESAI, $this->job->fresh()->cached_status);
    }

    // ------------------------------------------------------------------
    // BUG 6: Kronologi menolak milestone yang pendahulunya belum ada
    // ------------------------------------------------------------------

    #[Test]
    public function kronologi_menolak_milestone_tanpa_pendahulu(): void
    {
        $cases = [
            [Milestone::TYPE_SPPB, null, 'SPPB tanpa PIB Filed'],
            [Milestone::TYPE_DO_RELEASE, null, 'DO tanpa Discharge'],
            [Milestone::TYPE_GATE_OUT_FULL, $this->c1->id, 'Gate-Out tanpa DO/Discharge'],
            [Milestone::TYPE_DELIVERED, $this->c1->id, 'Delivered tanpa Gate-Out'],
            [Milestone::TYPE_GATE_IN_EMPTY, $this->c1->id, 'Gate-In tanpa Gate-Out'],
        ];

        foreach ($cases as [$type, $containerId, $label]) {
            try {
                Milestone::assertValidChronology($this->job, $type, Carbon::parse('2026-11-06 10:00:00'), $containerId);
            } catch (ValidationException) {
                continue;
            }
            $this->fail("Kronologi seharusnya menolak: {$label}");
        }

        $this->assertTrue(true); // semua kasus ditolak
    }

    // ------------------------------------------------------------------
    // BUG 5a: Trip show tidak lagi 500
    // ------------------------------------------------------------------

    #[Test]
    public function trip_show_menampilkan_halaman_bukan_500(): void
    {
        $trip = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
        ]);

        // BUGFIX 2026-11-04: tabel pivot trip_vendor_invoice tidak pernah ada
        // di migrasi — Trip show 500 "no such table" walau nama relasi sudah
        // diperbaiki. Buktikan pivot benar-benar bekerja: tautkan satu
        // vendor invoice lalu pastikan tampil di halaman.
        $vendorInvoice = VendorInvoice::create([
            'company_id' => $this->company->id,
            'vendor_id' => Vendor::factory()->create(['company_id' => $this->company->id])->id,
            'invoice_number' => 'VINV-TEST-001',
            'invoice_date' => '2026-11-05',
            'currency_code' => 'USD',
            'amount_usd' => 500,
            'rate_to_idr' => 16000,
            'amount_idr' => 8000000,
            'created_by_user_id' => $this->ops->id,
        ]);
        $trip->vendorInvoices()->attach($vendorInvoice->id);

        $response = $this->actingAs($this->ops)->get(route('trips.show', $trip->id));

        $response->assertOk(); // sebelumnya 500 RelationNotFoundException (vendorInvoices)
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('trips/show')
            ->has('trip.vendor_invoices', 1)
        );
    }

    // ------------------------------------------------------------------
    // BUG 5b: Laporan aging — bucket mengikuti hari telat yang sebenarnya
    // ------------------------------------------------------------------

    #[Test]
    public function aging_bucket_mengikuti_hari_telat(): void
    {
        $make = fn (string $due, int $total) => Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'status' => Invoice::STATUS_LOCKED,
            'due_date' => $due,
            'total_invoice' => $total,
            'total_paid' => 0,
        ]);

        $make(now()->subDays(20)->toDateString(), 1000000); // 0-30
        $make(now()->subDays(45)->toDateString(), 2000000); // 31-60
        $make(now()->subDays(75)->toDateString(), 3000000); // 61-90
        $make(now()->subDays(100)->toDateString(), 4000000); // >90
        $make(now()->addDays(10)->toDateString(), 5000000); // belum jatuh tempo

        $this->actingAs($this->finance)->get(route('reports.aging'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/aging')
                ->has('invoices', 5)
                ->where('invoices.0.bucket', '0-30')
                ->where('invoices.0.days_overdue', 20)
                ->where('invoices.1.bucket', '31-60')
                ->where('invoices.2.bucket', '61-90')
                ->where('invoices.3.bucket', '>90')
                ->where('invoices.4.bucket', '0-30')
                ->where('invoices.4.days_overdue', 0)
            );
    }

    // ------------------------------------------------------------------
    // BUG 5c: Laporan demurrage — hari kena biaya tidak lagi selalu 0
    // ------------------------------------------------------------------

    #[Test]
    public function laporan_demurrage_menghitung_hari_kena_biaya(): void
    {
        $this->job->update(['ata' => '2026-11-03']);

        $this->job->milestones()->createMany([
            [
                'company_id' => $this->company->id,
                'container_id' => $this->c1->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_GATE_OUT_FULL,
                'occurred_at' => '2026-11-14 10:00:00',
                'recorded_at' => now(),
                'is_active' => true,
            ],
            [
                'company_id' => $this->company->id,
                'container_id' => $this->c1->id,
                'branch' => Milestone::BRANCH_LOGISTIK,
                'milestone_type' => Milestone::TYPE_GATE_IN_EMPTY,
                'occurred_at' => '2026-11-25 10:00:00',
                'recorded_at' => now(),
                'is_active' => true,
            ],
        ]);

        // ATA 3 Nov → gate-out 14 Nov = 11 hari − free 7 = 4 hari demurrage;
        // gate-out 14 Nov → gate-in 25 Nov = 11 hari − free 7 = 4 hari detention.
        $this->actingAs($this->finance)->get(route('reports.demurrage'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/demurrage-per-line')
                ->has('summary', 1)
                ->where('summary.0.total_demurrage_days', 4)
                ->where('summary.0.total_detention_days', 4)
            );
    }

    // ------------------------------------------------------------------
    // BUG 5d: Laporan estimate-vs-actual tidak lagi 500
    // ------------------------------------------------------------------

    #[Test]
    public function laporan_estimate_vs_actual_tampil_dan_menghitung_selisih(): void
    {
        JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => JobCost::TYPE_THC,
            'payer_flag' => JobCost::PAYER_PPJK,
            'currency_code' => 'IDR',
            'amount_valas' => 1000000,
            'kurs_internal_rate' => 1,
            'amount_idr' => 1000000,
            'status' => JobCost::STATUS_ESTIMASI,
            'created_by_user_id' => $this->cs->id,
        ]);
        JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => JobCost::TYPE_THC,
            'payer_flag' => JobCost::PAYER_PPJK,
            'currency_code' => 'IDR',
            'amount_valas' => 1200000,
            'kurs_internal_rate' => 1,
            'amount_idr' => 1200000,
            'status' => JobCost::STATUS_AKTUAL,
            'created_by_user_id' => $this->cs->id,
        ]);

        $this->actingAs($this->finance)->get(route('reports.estimate-vs-actual'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/estimate-vs-actual')
                ->has('rows', 1)
                ->where('rows.0.total_estimasi', 1000000)
                ->where('rows.0.total_aktual', 1200000)
                ->where('rows.0.selisih', 200000)
            );
    }

    // ------------------------------------------------------------------
    // BUG 5e: Eksposur importir lunas penuh = 0, bukan negatif
    // ------------------------------------------------------------------

    #[Test]
    public function eksposur_importir_lunas_penuh_nol_bukan_negatif(): void
    {
        // Biaya aktual (sumber Blok B) Rp25.750.000
        JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => JobCost::TYPE_THC,
            'payer_flag' => JobCost::PAYER_PPJK,
            'currency_code' => 'IDR',
            'amount_valas' => 25750000,
            'kurs_internal_rate' => 1,
            'amount_idr' => 25750000,
            'status' => JobCost::STATUS_AKTUAL,
            'created_by_user_id' => $this->cs->id,
        ]);

        // Invoice: Blok A 101.705.000 + Blok C 2.775.000 → total 130.230.000
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'status' => Invoice::STATUS_LOCKED,
            'blok_a_total' => 101705000,
            'blok_b_total' => 25750000,
            'blok_c_service_fee' => 2500000,
            'blok_c_ppn' => 275000,
            'total_invoice' => 130230000,
            'total_paid' => 0,
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'importir_id' => $this->importir->id,
            'payment_date' => '2026-11-10',
            'amount' => 130230000,
            'method' => Payment::METHOD_TRANSFER,
            'is_unallocated' => true,
            'created_by_user_id' => $this->finance->id,
        ]);
        PaymentAllocation::create([
            'company_id' => $this->company->id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'job_id' => $this->job->id,
            'amount' => 130230000,
            'blok' => 'a',
            'allocated_by_user_id' => $this->finance->id,
        ]);

        // Rumus lama: 25.750.000 − 130.230.000 = −104.480.000 (negatif).
        // Rumus benar: 25.750.000 + 101.705.000 + 2.775.000 − 130.230.000 = 0.
        $this->actingAs($this->finance)->get(route('reports.exposure'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/exposure')
                ->has('exposures', 1)
                ->where('exposures.0.exposure_bruto', 0)
            );

        $this->actingAs($this->finance)->get(route('finance.exposure'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('finance/exposure')
                ->where('exposures.0.exposure_bruto', 0)
            );
    }
}
