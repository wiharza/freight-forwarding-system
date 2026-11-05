<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContainerType;
use App\Models\CustomsDeclaration;
use App\Models\Importir;
use App\Models\InternalExchangeRate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Location;
use App\Models\MfnTariff;
use App\Models\Payment;
use App\Models\Pph22Rule;
use App\Models\ShippingLine;
use App\Models\TaxExchangeRate;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PibCalculator;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi untuk 6 temuan tambahan review kode 2026-11-04
 * (perbaiki-temuan-tambahan, master 3047e32).
 */
class TemuanTambahanTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private Company $company2;

    private User $cs;

    private User $ops;

    private User $finance;

    private User $customs;

    private User $customs2;

    private Importir $importir;

    private Importir $importir2;

    private Importir $importirB;

    private ShippingLine $line;

    private Location $pol;

    private Location $pod;

    private Job $job;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        $this->company2 = Company::factory()->create();

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->customs = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs->roles()->sync([$this->roleId('customs_specialist')]);

        $this->customs2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs2->roles()->sync([$this->roleId('customs_specialist')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        // Importir perusahaan lain (company 2)
        $this->importir2 = Importir::factory()->create(['company_id' => $this->company2->id]);
        // Importir kedua dalam company yang sama (untuk temuan #3)
        $this->importirB = Importir::factory()->create(['company_id' => $this->company->id]);

        $this->line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $this->pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $this->pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'pol_id' => $this->pol->id,
            'pod_id' => $this->pod->id,
        ]);
        $this->job->containers()->create([
            'container_number' => 'MSKU1234565',
            'size' => "40'",
            'type' => 'High Cube',
            'seal_number' => 'SEAL-001',
            'gross_weight_kg' => 25000,
        ]);

        $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id]);

        ContainerType::query()->create([
            'company_id' => $this->company->id,
            'size' => "40'",
            'type' => 'High Cube',
        ]);

        app(CompanyContext::class)->set($this->company->id);
    }

    // ------------------------------------------------------------------
    // Temuan #1 — FK lintas-tenant pada Job & Trip
    // ------------------------------------------------------------------

    #[Test]
    public function job_ditolak_bila_importir_milik_company_lain(): void
    {
        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $this->jobPayload([
            'importir_id' => $this->importir2->id,
        ]));

        $response->assertSessionHasErrors(['importir_id']);
        $this->assertDatabaseMissing('jobs', ['bl_number' => 'BLSKU123456']);
    }

    #[Test]
    public function job_ditolak_bila_pod_milik_company_lain(): void
    {
        $podLain = Location::factory()->create(['company_id' => $this->company2->id, 'type' => Location::TYPE_PORT]);

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $this->jobPayload([
            'pod_id' => $podLain->id,
        ]));

        $response->assertSessionHasErrors(['pod_id']);
        $this->assertDatabaseMissing('jobs', ['bl_number' => 'BLSKU123456']);
    }

    #[Test]
    public function job_diterima_bila_semua_fk_milik_company_sendiri(): void
    {
        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $this->jobPayload());

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jobs', ['bl_number' => 'BLSKU123456']);
    }

    #[Test]
    public function trip_ditolak_bila_vendor_milik_company_lain(): void
    {
        $vendorLain = Vendor::factory()->create(['company_id' => $this->company2->id]);

        $response = $this->actingAs($this->ops)->post(route('trips.store'), $this->tripPayload([
            'vendor_id' => $vendorLain->id,
        ]));

        $response->assertSessionHasErrors(['vendor_id']);
        $this->assertDatabaseMissing('trips', ['trip_number' => 'TRP-TEST-001']);
    }

    #[Test]
    public function trip_ditolak_bila_job_milik_company_lain(): void
    {
        $jobLain = Job::factory()->create([
            'company_id' => $this->company2->id,
            'importir_id' => $this->importir2->id,
        ]);

        $response = $this->actingAs($this->ops)->post(route('trips.store'), $this->tripPayload([
            'job_id' => $jobLain->id,
        ]));

        $response->assertSessionHasErrors(['job_id']);
        $this->assertDatabaseMissing('trips', ['trip_number' => 'TRP-TEST-001']);
    }

    // ------------------------------------------------------------------
    // Temuan #2 — approve PIB memblokir tarif belum terverifikasi (ADR 0008)
    // ------------------------------------------------------------------

    #[Test]
    public function approve_ditolak_bila_tarif_mfn_belum_terverifikasi(): void
    {
        MfnTariff::create([
            'company_id' => $this->company->id,
            'hs_code' => '3926.90.99',
            'description' => 'Barang plastik lain',
            'bm_rate' => 0.10,
            'effective_from' => '2022-04-01',
            'verification_status' => MfnTariff::STATUS_UNVERIFIED,
        ]);

        $declaration = $this->buatDrafPib($this->job, '3926909900');

        $this->assertTrue($declaration->items()->firstOrFail()->is_unverified_warning);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(
            CustomsDeclaration::STATUS_DRAFT,
            $declaration->fresh()->status
        );
    }

    #[Test]
    public function approve_ditolak_bila_aturan_pph22_belum_terverifikasi(): void
    {
        Pph22Rule::create([
            'company_id' => $this->company->id,
            'hs_code' => '33.04',
            'lampiran' => 'A',
            'rate' => 0.10,
            'verification_status' => Pph22Rule::STATUS_UNVERIFIED,
        ]);

        $declaration = $this->buatDrafPib($this->job, '3304990000');

        $this->assertTrue($declaration->items()->firstOrFail()->is_unverified_warning);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(
            CustomsDeclaration::STATUS_DRAFT,
            $declaration->fresh()->status
        );
    }

    #[Test]
    public function approve_diterima_bila_semua_tarif_terverifikasi(): void
    {
        $declaration = $this->buatDrafPib($this->job, '8471302000');

        $this->assertFalse($declaration->items()->firstOrFail()->is_unverified_warning);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            CustomsDeclaration::STATUS_APPROVED,
            $declaration->fresh()->status
        );
    }

    // ------------------------------------------------------------------
    // Temuan #3 — alokasi ke invoice importir lain
    // ------------------------------------------------------------------

    #[Test]
    public function alokasi_ditolak_bila_importir_pembayaran_berbeda_dari_invoice(): void
    {
        // Invoice milik importir B (company sama, importir beda)
        $jobB = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importirB->id,
            'shipping_line_id' => $this->line->id,
        ]);
        $invoiceB = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $jobB->id,
            'importir_id' => $this->importirB->id,
            'total_invoice' => 1000000,
            'status' => Invoice::STATUS_DRAFT,
        ]);
        $itemB = $invoiceB->items()->create([
            'description' => 'Jasa PPJK',
            'blok' => 'c',
            'total_line' => 1000000,
        ]);

        // Pembayaran tercatat untuk importir A (invoice milik A)
        $invoiceA = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'total_invoice' => 1000000,
            'status' => Invoice::STATUS_DRAFT,
        ]);
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoiceA->id,
            'importir_id' => $this->importir->id,
            'payment_date' => '2026-11-04',
            'amount' => 1000000,
            'method' => Payment::METHOD_TRANSFER,
            'is_unallocated' => true,
        ]);

        // Coba alokasikan payment importir A ke invoice importir B
        $response = $this->actingAs($this->finance)->post(
            route('finance.payments.allocate', $payment->id),
            [
                'invoice_id' => $invoiceB->id,
                'allocations' => [
                    ['invoice_item_id' => $itemB->id, 'amount' => 500000, 'blok' => 'c'],
                ],
            ]
        );

        $response->assertSessionHasErrors(['invoice_id']);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertEquals(0, (float) $invoiceB->fresh()->total_paid);
    }

    // ------------------------------------------------------------------
    // Temuan #4 — kurs yang hilang tidak boleh diam-diam menjadi 1.0
    // ------------------------------------------------------------------

    #[Test]
    public function kalkulator_gagal_bila_kurs_pajak_valas_tidak_ada(): void
    {
        $this->expectException(ValidationException::class);

        (new PibCalculator)->calculateItem([
            'hs_code' => '8471302000',
            'currency_code' => 'USD',
            'fob_valas' => 10000,
            'freight_valas' => 500,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 10,
            // sengaja TANPA kurs_pajak_rate dan TANPA baris TaxExchangeRate
        ]);
    }

    #[Test]
    public function kalkulator_idr_tetap_berjalan_tanpa_kurs(): void
    {
        $res = (new PibCalculator)->calculateItem([
            'hs_code' => '8471302000',
            'currency_code' => 'IDR',
            'fob_valas' => 10000000,
            'freight_valas' => 500000,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 0,
            // tanpa kurs_pajak_rate: IDR = 1.0 menurut definisi
        ]);

        $this->assertEqualsWithDelta(10500000, (float) $res['cif_rupiah'], 0.01);
    }

    #[Test]
    public function kurs_internal_gagal_bila_tidak_ada_baris_sama_sekali(): void
    {
        $this->expectException(LogicException::class);

        InternalExchangeRate::resolveRate('USD', Carbon::parse('2026-11-04'));
    }

    #[Test]
    public function kurs_internal_idr_tetap_satu_tanpa_baris(): void
    {
        $info = InternalExchangeRate::resolveRate('IDR', Carbon::parse('2026-11-04'));

        $this->assertSame('1.0000', $info['rate']);
    }

    // ------------------------------------------------------------------
    // Temuan #5 — PPh 22 emas batangan 0,25% (PMK 51/2025, Lampiran D)
    // ------------------------------------------------------------------

    #[Test]
    public function emas_batangan_dikenai_pph22_0_25_persen(): void
    {
        TaxExchangeRate::query()->firstOrCreate(
            ['kmk_number' => 'KMK-TEST-16000'],
            [
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
                'currency_code' => 'USD',
                'rate_to_idr' => '16000',
                'source' => 'test',
            ]
        );

        Pph22Rule::create([
            'company_id' => $this->company->id,
            'hs_code' => '71.08',
            'lampiran' => 'D',
            'rate' => 0.0025,
            'verification_status' => Pph22Rule::STATUS_VERIFIED,
        ]);

        $res = (new PibCalculator)->calculateItem([
            'hs_code' => '71081000',
            'currency_code' => 'USD',
            'fob_valas' => 10000,
            'freight_valas' => 0,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 0,
            'kurs_pajak_rate' => 16000,
        ], 'API-U');

        // CIF Rp160.000.000 × 0,25% = Rp400.000 (sebelum perbaikan: 2,5% = Rp4.000.000)
        $this->assertSame('emas_batangan_0.25', $res['pph22_category']);
        $this->assertEqualsWithDelta(0.0025, (float) $res['pph22_rate'], 0.0001);
        $this->assertEqualsWithDelta(400000, (float) $res['pph22_amount'], 0.01);
    }

    // ------------------------------------------------------------------
    // Temuan #6 — status trip tidak boleh melompati tahapan
    // ------------------------------------------------------------------

    #[Test]
    public function trip_tidak_bisa_lompat_assigned_ke_delivered(): void
    {
        $trip = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'status' => Trip::STATUS_ASSIGNED,
        ]);

        $response = $this->actingAs($this->ops)->patch(route('trips.status', $trip->id), [
            'status' => Trip::STATUS_DELIVERED,
            'actual_arrival' => '2026-11-05 10:00:00',
        ]);

        $response->assertSessionHasErrors(['status']);
        $this->assertSame(Trip::STATUS_ASSIGNED, $trip->fresh()->status);
        $this->assertNull($trip->fresh()->actual_arrival);
    }

    #[Test]
    public function trip_rantai_legal_diterima(): void
    {
        $trip = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'status' => Trip::STATUS_ASSIGNED,
        ]);

        $this->actingAs($this->ops)->patch(route('trips.status', $trip->id), [
            'status' => Trip::STATUS_CONFIRMED,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->ops)->patch(route('trips.status', $trip->id), [
            'status' => Trip::STATUS_IN_TRANSIT,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->ops)->patch(route('trips.status', $trip->id), [
            'status' => Trip::STATUS_DELIVERED,
            'actual_arrival' => '2026-11-05 10:00:00',
        ])->assertSessionHasNoErrors();

        $trip->refresh();
        $this->assertSame(Trip::STATUS_DELIVERED, $trip->status);
        $this->assertNotNull($trip->actual_arrival);
    }

    #[Test]
    public function trip_cancelled_ditolak_dari_in_transit_dan_delivered_bersifat_final(): void
    {
        $inTransit = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'status' => Trip::STATUS_IN_TRANSIT,
        ]);

        $this->actingAs($this->ops)->patch(route('trips.status', $inTransit->id), [
            'status' => Trip::STATUS_CANCELLED,
        ])->assertSessionHasErrors(['status']);
        $this->assertSame(Trip::STATUS_IN_TRANSIT, $inTransit->fresh()->status);

        $delivered = Trip::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'status' => Trip::STATUS_DELIVERED,
        ]);

        $this->actingAs($this->ops)->patch(route('trips.status', $delivered->id), [
            'status' => Trip::STATUS_CONFIRMED,
        ])->assertSessionHasErrors(['status']);
        $this->assertSame(Trip::STATUS_DELIVERED, $delivered->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buatDrafPib(Job $job, string $hsCode): CustomsDeclaration
    {
        // TEMUAN LANJUTAN (2026-11-05): kalkulator selalu resolve Kurs Pajak
        // dari baris resmi — draf butuh baris USD 16000 (di-seed di sini,
        // bukan setUp, supaya test "kurs hilang gagal" tetap tanpa baris).
        TaxExchangeRate::query()->firstOrCreate(
            ['kmk_number' => 'KMK-TEST-16000'],
            [
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
                'currency_code' => 'USD',
                'rate_to_idr' => '16000',
                'source' => 'test',
            ]
        );

        $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => $hsCode,
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'kurs_pajak_rate' => '16000',
            ]],
        ])->assertSessionHasNoErrors();

        return CustomsDeclaration::query()->where('job_id', $job->id)->latest('version')->firstOrFail();
    }

    private function jobPayload(array $overrides = []): array
    {
        return array_merge([
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'bl_number' => 'BLSKU123456',
            'bl_date' => '2026-11-03',
            'pol_id' => $this->pol->id,
            'pod_id' => $this->pod->id,
            'containers' => [[
                'container_number' => 'MSKU1234565',
                'size' => "40'",
                'type' => 'High Cube',
                'seal_number' => 'SEAL-001',
                'gross_weight_kg' => '25000',
            ]],
        ], $overrides);
    }

    private function tripPayload(array $overrides = []): array
    {
        return array_merge([
            'job_id' => $this->job->id,
            'vendor_id' => $this->vendor->id,
            'trip_number' => 'TRP-TEST-001',
            'scheduled_date' => '2026-11-05',
            'container_ids' => [$this->job->containers()->firstOrFail()->id],
        ], $overrides);
    }
}
