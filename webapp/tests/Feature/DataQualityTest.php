<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContainerType;
use App\Models\CustomsDeclaration;
use App\Models\Importir;
use App\Models\Job;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Models\TaxExchangeRate;
use App\Models\User;
use App\Models\Vessel;
use App\Support\CompanyContext;
use App\Support\FormatRules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * DQ-18 s.d. DQ-22 — pembasmian salah ketik pada field yang menentukan
 * uang dan hukum: kapal/IMO, nomor kontainer (ISO 6346), NPWP, kode HS,
 * incoterm, dan periode Kurs Pajak Rabu→Selasa.
 */
class DataQualityTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $admin;

    private User $cs;

    private User $customs;

    private User $customs2;

    private Importir $importir;

    private ShippingLine $line;

    private Location $pod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();

        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->roles()->sync([$this->roleId('admin')]);

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->customs = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs->roles()->sync([$this->roleId('customs_specialist')]);

        // Checker kedua untuk maker-checker (ADR 0006) — gate approve_snapshot
        // hanya milik customs_specialist (config/features.php §2.5)
        $this->customs2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs2->roles()->sync([$this->roleId('customs_specialist')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $this->line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $this->pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        foreach ([['20', 'Dry'], ['40', 'High Cube']] as [$size, $type]) {
            ContainerType::query()->create(['company_id' => $this->company->id, 'size' => $size, 'type' => $type]);
        }

        app(CompanyContext::class)->set($this->company->id);

        // TEMUAN LANJUTAN (2026-11-05): kalkulator selalu resolve Kurs Pajak
        // dari baris resmi — draf-draf di file ini butuh baris USD 15800.
        TaxExchangeRate::query()->create([
            'kmk_number' => 'KMK-TEST-15800',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'currency_code' => 'USD',
            'rate_to_idr' => '15800',
            'source' => 'test',
        ]);
    }

    private function jobPayload(array $overrides = []): array
    {
        return array_merge([
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
            'bl_number' => 'BL-DQ-001',
            'bl_date' => '2026-11-01',
            'pod_id' => $this->pod->id,
            'claim_fta' => false,
            'is_fcl' => true,
            'containers' => [
                [
                    'container_number' => 'MSKU1234565',
                    'size' => '20',
                    'type' => 'Dry',
                    'gross_weight_kg' => '21000',
                ],
            ],
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // DQ-18: Master Vessel + IMO check digit
    // ------------------------------------------------------------------

    #[Test]
    public function master_kapal_menerima_imo_valid_dengan_awalan_imo(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.master.vessels.store'), [
            'name' => 'SITC SHANGHAI',
            'imo_number' => 'IMO 9074729',
            'call_sign' => '9HA4567',
            'shipping_line_id' => $this->line->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vessels', [
            'name' => 'SITC SHANGHAI',
            'imo_number' => '9074729', // dinormalisasi: tanpa awalan IMO
        ]);
    }

    #[Test]
    public function master_kapal_menolak_imo_dengan_check_digit_salah(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.master.vessels.store'), [
            'name' => 'EVER GIVEN',
            'imo_number' => 'IMO 9074728', // digit terakhir salah (harus 9)
        ]);

        $response->assertSessionHasErrors(['imo_number']);
        $this->assertDatabaseMissing('vessels', ['name' => 'EVER GIVEN']);
    }

    #[Test]
    public function master_kapal_menolak_imo_bukan_7_digit(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.master.vessels.store'), [
            'name' => 'TANJUNG PERAK',
            'imo_number' => '12345',
        ]);

        $response->assertSessionHasErrors(['imo_number']);
    }

    #[Test]
    public function entri_job_dengan_vessel_id_menyimpan_snapshot_nama_kapal(): void
    {
        $vessel = Vessel::query()->create([
            'company_id' => $this->company->id,
            'name' => 'MSC DANIELA',
            'imo_number' => '1234567',
        ]);

        $payload = $this->jobPayload([
            'vessel_id' => $vessel->id,
            'vessel_name' => 'NAMA SALAH KETIK', // harus ditimpa snapshot dari master
        ]);

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $payload);
        $response->assertSessionHasNoErrors();

        $job = Job::query()->where('bl_number', 'BL-DQ-001')->firstOrFail();
        $this->assertEquals($vessel->id, $job->vessel_id);
        $this->assertEquals($vessel->name, $job->vessel_name); // snapshot dari master
    }

    #[Test]
    public function entri_job_menolak_vessel_dari_company_lain(): void
    {
        $otherCompany = Company::factory()->create();
        // forceFill: company_id tidak fillable (diisi otomatis dari CompanyContext
        // oleh trait BelongsToCompany) — di sini kita sengaja menulis company lain.
        $vessel = (new Vessel)->forceFill([
            'company_id' => $otherCompany->id,
            'name' => 'KAPAL LAIN',
        ]);
        $vessel->save();

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $this->jobPayload([
            'vessel_id' => $vessel->id,
        ]));

        $response->assertSessionHasErrors(['vessel_id']);
    }

    // ------------------------------------------------------------------
    // DQ-19: Nomor kontainer ISO 6346
    // ------------------------------------------------------------------

    #[Test]
    public function nomor_kontainer_dengan_check_digit_salah_ditolak(): void
    {
        $payload = $this->jobPayload();
        $payload['containers'][0]['container_number'] = 'MSKU1234567'; // check digit harus 2

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $payload);

        $response->assertSessionHasErrors(['containers.0.container_number']);
    }

    #[Test]
    public function nomor_kontainer_lowercase_dan_pemisah_dinormalisasi(): void
    {
        $payload = $this->jobPayload();
        $payload['containers'][0]['container_number'] = 'msku 1234-565';

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('containers', ['container_number' => 'MSKU1234565']);
    }

    #[Test]
    public function nomor_kontainer_format_bukan_iso6346_ditolak(): void
    {
        $payload = $this->jobPayload();
        $payload['containers'][0]['container_number'] = 'AB123'; // terlalu pendek

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $payload);

        $response->assertSessionHasErrors(['containers.0.container_number']);
    }

    #[Test]
    public function util_check_digit_iso6346_dan_imo_benar(): void
    {
        $this->assertTrue(FormatRules::isIso6346Valid('MSKU1234565'));
        $this->assertFalse(FormatRules::isIso6346Valid('MSKU1234567'));
        $this->assertSame(5, FormatRules::iso6346CheckDigit('MSKU1234565'));
        $this->assertTrue(FormatRules::imoCheckDigitValid('9074729'));
        $this->assertTrue(FormatRules::imoCheckDigitValid('1234567'));
        $this->assertFalse(FormatRules::imoCheckDigitValid('9074728'));
        $this->assertSame('9074729', FormatRules::normalizeImo('IMO 9074729'));
        $this->assertSame('012345678901234', FormatRules::normalizeNpwp('01.234.567.8-901.234'));
        $this->assertSame('84713020', FormatRules::normalizeHsCode('8471.30.20'));
    }

    // ------------------------------------------------------------------
    // DQ-22a: (size, type) kontainer dari master
    // ------------------------------------------------------------------

    #[Test]
    public function kombinasi_ukuran_tipe_di_luar_master_ditolak(): void
    {
        $payload = $this->jobPayload();
        $payload['containers'][0]['type'] = 'Tank'; // tidak ada di master

        $response = $this->actingAs($this->cs)->post(route('jobs.store'), $payload);

        $response->assertSessionHasErrors(['containers.0.type']);
    }

    // ------------------------------------------------------------------
    // DQ-20: NPWP & kode HS
    // ------------------------------------------------------------------

    #[Test]
    public function importir_menerima_npwp_15_dan_16_digit(): void
    {
        $ok15 = $this->actingAs($this->admin)->post(route('admin.master.importirs.store'), [
            'name' => 'PT Lima Belas',
            'npwp' => '01.234.567.8-012.000', // 15 digit berformat
            'status_api' => 'API-U',
        ]);
        $ok15->assertSessionHasNoErrors();

        $ok16 = $this->actingAs($this->admin)->post(route('admin.master.importirs.store'), [
            'name' => 'PT Enam Belas',
            'npwp' => '3173265101234567', // 16 digit NIK (PMK 112/2022)
            'status_api' => 'API-P',
        ]);
        $ok16->assertSessionHasNoErrors();
    }

    #[Test]
    public function importir_dengan_npwp_format_kacau_ditolak(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.master.importirs.store'), [
            'name' => 'PT Salah Ketik',
            'npwp' => '01-234-5678', // hanya 9 digit
            'status_api' => 'API-U',
        ]);

        $response->assertSessionHasErrors(['npwp']);
    }

    #[Test]
    public function master_mfn_menormalisasi_kode_hs_berformat_titik(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.master.mfn-tariffs.store'), [
            'hs_code' => '8471.30.20',
            'bm_rate' => '0.05',
            'effective_from' => '2026-11-01',
            'verification_status' => 'terverifikasi',
            'legal_basis' => 'PMK 62/2025 Lampiran I',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mfn_tariffs', ['hs_code' => '84713020']);
    }

    #[Test]
    public function master_mfn_menolak_kode_hs_bukan_8_atau_10_digit(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.master.mfn-tariffs.store'), [
            'hs_code' => '8471', // terlalu pendek
            'bm_rate' => '0.05',
            'effective_from' => '2026-11-01',
            'verification_status' => 'terverifikasi',
            'legal_basis' => 'PMK 62/2025 Lampiran I',
        ]);

        $response->assertSessionHasErrors(['hs_code']);
        $this->assertDatabaseMissing('mfn_tariffs', ['hs_code' => '8471']);
    }

    #[Test]
    public function draf_pib_menolak_kode_hs_tak_valid_dan_tersimpan_dinormalisasi(): void
    {
        $job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
        ]);

        $baseItem = [
            'currency_code' => 'USD',
            'fob_valas' => '10000',
            'freight_valas' => '500',
            'insurance_valas' => '10',
            'insurance_basis' => 'input_riil',
            'kurs_pajak_rate' => '15800',
        ];

        // Kode HS tak valid → ditolak
        $bad = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $job->id), [
            'items' => [array_merge($baseItem, ['hs_code' => '0301.11'])],
        ]);
        $bad->assertSessionHasErrors(['items.0.hs_code']);

        // Kode HS valid berformat → tersimpan normal 10 digit
        $good = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $job->id), [
            'items' => [array_merge($baseItem, ['hs_code' => '0301.11.00.00'])],
        ]);
        $good->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customs_items', ['hs_code' => '0301110000']);
    }

    // ------------------------------------------------------------------
    // DQ-21: Incoterm pada draf PIB
    // ------------------------------------------------------------------

    #[Test]
    public function draf_pib_menerima_incoterm_cif_dari_daftar_incoterms_2020(): void
    {
        $job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
        ]);

        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $job->id), [
            'incoterm' => 'CIF',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_valas' => '10',
                'insurance_basis' => 'input_riil',
                'kurs_pajak_rate' => '15800',
            ]],
        ]);

        $response->assertSessionHasNoErrors();
        $declaration = CustomsDeclaration::query()->where('job_id', $job->id)->latest('version')->firstOrFail();
        $this->assertSame('CIF', $declaration->incoterm);
    }

    #[Test]
    public function draf_pib_menolak_kode_incoterm_di_luar_daftar(): void
    {
        $job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
        ]);

        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $job->id), [
            'incoterm' => 'XYZ',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'kurs_pajak_rate' => '15800',
            ]],
        ]);

        $response->assertSessionHasErrors(['incoterm']);
    }

    // ------------------------------------------------------------------
    // DQ-22b: Periode Kurs Pajak Rabu → Selasa (PV-3)
    // ------------------------------------------------------------------

    #[Test]
    public function kurs_pajak_yang_dimulai_bukan_rabu_ditolak(): void
    {
        // 2026-11-02 = Senin
        $response = $this->actingAs($this->admin)->post(route('admin.master.tax-rates.store'), [
            'kmk_number' => 'KMK 100/MK/EF.2/2026',
            'valid_from' => '2026-11-02',
            'valid_to' => '2026-11-08',
            'currency_code' => 'USD',
            'rate_to_idr' => '15800',
            'source' => 'manual',
        ]);

        $response->assertSessionHasErrors(['valid_from']);
    }

    #[Test]
    public function kurs_pajak_rabu_sampai_selasa_diterima(): void
    {
        // 2026-11-04 = Rabu, 2026-11-10 = Selasa (+6 hari)
        $response = $this->actingAs($this->admin)->post(route('admin.master.tax-rates.store'), [
            'kmk_number' => 'KMK 101/MK/EF.2/2026',
            'valid_from' => '2026-11-04',
            'valid_to' => '2026-11-10',
            'currency_code' => 'USD',
            'rate_to_idr' => '15850',
            'source' => 'manual',
        ]);

        $response->assertSessionHasNoErrors();
        $rate = TaxExchangeRate::query()->where('kmk_number', 'KMK 101/MK/EF.2/2026')->firstOrFail();
        $this->assertSame('2026-11-04', $rate->valid_from->toDateString());
        $this->assertSame('2026-11-10', $rate->valid_to->toDateString());
    }

    #[Test]
    public function kurs_pajak_dengan_rentang_bukan_7_hari_ditolak(): void
    {
        // Rabu tapi hanya berlaku 3 hari
        $response = $this->actingAs($this->admin)->post(route('admin.master.tax-rates.store'), [
            'kmk_number' => 'KMK 102/MK/EF.2/2026',
            'valid_from' => '2026-11-04',
            'valid_to' => '2026-11-07',
            'currency_code' => 'USD',
            'rate_to_idr' => '15850',
            'source' => 'manual',
        ]);

        $response->assertSessionHasErrors(['valid_to']);
    }

    #[Test]
    public function resolve_rate_kurs_pajak_menggunakan_periode_rabu_selasa(): void
    {
        TaxExchangeRate::query()->create([
            'company_id' => $this->company->id,
            'kmk_number' => 'KMK 103/MK/EF.2/2026',
            'valid_from' => '2026-11-04', // Rabu
            'valid_to' => '2026-11-10',   // Selasa
            'currency_code' => 'USD',
            'rate_to_idr' => '15900',
            'source' => 'manual',
        ]);

        // Jumat 2026-11-06 masih dalam periode Rabu→Selasa
        $rate = TaxExchangeRate::resolveRate('USD', Carbon::parse('2026-11-06'));
        $this->assertNotNull($rate);
        $this->assertEquals('15900.0000', (string) $rate->rate_to_idr); // decimal:4
    }

    // ------------------------------------------------------------------
    // Kelengkapan rantai snapshot (Tiket 23): draf PIB → approve → acuan invoice Blok (a)
    // ------------------------------------------------------------------

    #[Test]
    public function draf_pib_disetujui_menjadi_snapshot_oleh_user_lain(): void
    {
        $job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->line->id,
        ]);

        $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'kurs_pajak_rate' => '15800',
            ]],
        ])->assertSessionHasNoErrors();

        $declaration = CustomsDeclaration::query()->where('job_id', $job->id)->latest('version')->firstOrFail();

        // Pembuat tidak boleh menyetujui sendiri (ADR 0006)
        $selfApprove = $this->actingAs($this->customs)->post(
            route('jobs.calculator.approve', ['job' => $job->id, 'declaration' => $declaration->id])
        );
        $selfApprove->assertSessionHasErrors(['maker_checker']);

        // Checker lain menyetujui → jadi snapshot
        $approve = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $job->id, 'declaration' => $declaration->id])
        );
        $approve->assertSessionHasNoErrors();

        $declaration->refresh();
        $this->assertSame(CustomsDeclaration::STATUS_APPROVED, $declaration->status);
        $this->assertEquals($this->customs2->id, $declaration->approved_by_user_id);
        $this->assertNotNull($declaration->approved_at);
    }
}
