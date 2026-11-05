<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\Job;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Models\TaxExchangeRate;
use App\Models\User;
use App\Services\PibCalculator;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes reproduksi + regresi temuan audit lanjutan 2026-11-05, item 1:
 * Kurs Pajak valas yang hilang tidak boleh menjadi 1.0 lewat default UI.
 */
class AuditLanjutanKursTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private Company $company2;

    private User $finance;

    private User $customs;

    private User $customs2;

    private Importir $importir;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        $this->company2 = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->customs = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs->roles()->sync([$this->roleId('customs_specialist')]);

        $this->customs2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs2->roles()->sync([$this->roleId('customs_specialist')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
            'pol_id' => $pol->id,
            'pod_id' => $pod->id,
        ]);

        // Kurs Pajak USD yang berlaku saat test berjalan (jendela lebar —
        // KMK riil mingguan, tapi test butuh baris yang pasti mencakup now()).
        TaxExchangeRate::query()->create([
            'company_id' => $this->company->id,
            'kmk_number' => 'KMK-TEST-16000',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'currency_code' => 'USD',
            'rate_to_idr' => '16000',
            'source' => 'test',
        ]);
    }

    // ------------------------------------------------------------------
    // Temuan 1 — kurs valas yang hilang tidak boleh menjadi 1.0
    // ------------------------------------------------------------------

    #[Test]
    public function kalkulator_menolak_kurs_klien_1_tanpa_baris_kurs_pajak(): void
    {
        // Hapus baris kurs yang di-seed di setUp: tidak ada Kurs Pajak USD.
        TaxExchangeRate::query()->delete();

        $this->expectException(ValidationException::class);

        // UI lama mengirim default kurs 1 saat tidak ada Kurs Pajak aktif —
        // sebelum perbaikan nilai ini dipakai diam-diam (CIF Rp 10.510).
        (new PibCalculator)->calculateItem([
            'hs_code' => '8471302000',
            'currency_code' => 'USD',
            'fob_valas' => 10000,
            'freight_valas' => 500,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 10,
            'kurs_pajak_rate' => 1,
        ]);
    }

    #[Test]
    public function kalkulator_memakai_baris_kurs_pajak_bukan_input_klien(): void
    {
        // Baris KMK 16000 ada; klien mengirim kurs 1 (atau kurs lain yang
        // salah) — hasil harus tetap memakai 16000 dari baris resmi.
        $res = (new PibCalculator)->calculateItem([
            'hs_code' => '8471302000',
            'currency_code' => 'USD',
            'fob_valas' => 10000,
            'freight_valas' => 500,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 10,
            'kurs_pajak_rate' => 1,
        ]);

        $this->assertEqualsWithDelta(16000, (float) $res['kurs_pajak_rate'], 0.0001);
        // CIF valas 10.510 × 16.000 = Rp 168.160.000
        $this->assertEqualsWithDelta(168160000, (float) $res['cif_rupiah'], 0.01);
    }

    #[Test]
    public function draf_pib_ditolak_bila_kurs_pajak_tidak_ada_meski_ui_mengirim_1(): void
    {
        TaxExchangeRate::query()->delete();

        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'kurs_pajak_rate' => '1',
            ]],
        ]);

        $response->assertSessionHasErrors(['kurs_pajak_rate']);
        $this->assertDatabaseCount('customs_declarations', 0);
    }
}
