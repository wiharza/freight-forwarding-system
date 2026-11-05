<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomsDeclaration;
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
 * Tes reproduksi + regresi temuan audit lanjutan 2026-11-05, item 3:
 * PPh 22 — basis lelang = harga jual lelang; pembebasan Pasal 4 wajib
 * alasan + referensi dokumen pendukung.
 */
class AuditLanjutanPph22Test extends TestCase
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
    // Temuan 3a — PPh 22 barang lelang: 7,5% dari harga jual lelang
    // ------------------------------------------------------------------

    #[Test]
    public function lelang_dihitung_7_5_persen_dari_harga_jual_lelang(): void
    {
        $res = (new PibCalculator)->calculateItem([
            'hs_code' => '8471302000', // HS Lampiran A (10%) — flag lelang harus menang
            'currency_code' => 'USD',
            'fob_valas' => 10000,
            'freight_valas' => 500,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 10,
            'is_auction' => true,
            'auction_price_idr' => 100000000,
        ], 'API-U');

        $this->assertSame('lelang_7.5', $res['pph22_category']);
        $this->assertEqualsWithDelta(0.075, (float) $res['pph22_rate'], 0.0001);
        // 7,5% × Rp 100.000.000 = Rp 7.500.000 (BUKAN 10% × Nilai Impor)
        $this->assertEqualsWithDelta(7500000, (float) $res['pph22_amount'], 0.01);
    }

    #[Test]
    public function lelang_tanpa_harga_lelang_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        (new PibCalculator)->calculateItem([
            'hs_code' => '8471302000',
            'currency_code' => 'USD',
            'fob_valas' => 10000,
            'freight_valas' => 500,
            'insurance_basis' => 'input_riil',
            'insurance_valas' => 10,
            'is_auction' => true,
            // sengaja tanpa auction_price_idr
        ]);
    }

    #[Test]
    public function draf_pib_lelang_tersimpan_dengan_kategori_lelang(): void
    {
        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'is_auction' => true,
                'auction_price_idr' => '100000000',
            ]],
        ]);

        $response->assertSessionHasNoErrors();
        $item = CustomsDeclaration::query()->where('job_id', $this->job->id)->firstOrFail()->items()->firstOrFail();
        $this->assertSame('lelang_7.5', $item->pph22_category);
        $this->assertEqualsWithDelta(7500000, (float) $item->pph22_amount, 0.01);
    }

    // ------------------------------------------------------------------
    // Temuan 3b — pembebasan PPh 22 Pasal 4 wajib alasan + dokumen
    // ------------------------------------------------------------------

    #[Test]
    public function pembebasan_tanpa_alasan_ditolak(): void
    {
        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'is_pph22_exempt' => true,
                // sengaja tanpa exemption_reason dan exemption_document_ref
            ]],
        ]);

        $response->assertSessionHasErrors(['items.0.exemption_reason']);
        $this->assertDatabaseCount('customs_declarations', 0);
    }

    #[Test]
    public function pembebasan_tanpa_dokumen_pendukung_ditolak(): void
    {
        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'is_pph22_exempt' => true,
                'exemption_reason' => 'Hibah bencana alam — SK Menkeu No. 123/2026.',
                // sengaja tanpa exemption_document_ref
            ]],
        ]);

        $response->assertSessionHasErrors(['items.0.exemption_document_ref']);
        $this->assertDatabaseCount('customs_declarations', 0);
    }

    #[Test]
    public function pembebasan_dengan_alasan_dan_dokumen_diterima(): void
    {
        $response = $this->actingAs($this->customs)->post(route('jobs.calculator.draft', $this->job->id), [
            'incoterm' => 'FOB',
            'items' => [[
                'hs_code' => '8471302000',
                'currency_code' => 'USD',
                'fob_valas' => '10000',
                'freight_valas' => '500',
                'insurance_basis' => 'input_riil',
                'insurance_valas' => '10',
                'is_pph22_exempt' => true,
                'exemption_reason' => 'Hibah bencana alam — SK Menkeu No. 123/2026.',
                'exemption_document_ref' => 'SK-123/MK/2026 (terlampir di checklist dokumen)',
            ]],
        ]);

        $response->assertSessionHasNoErrors();
        $item = CustomsDeclaration::query()->where('job_id', $this->job->id)->firstOrFail()->items()->firstOrFail();
        $this->assertTrue((bool) $item->is_pph22_exempt);
        $this->assertSame('bebas_pasal_4', $item->pph22_category);
        $this->assertSame('0.0000', (string) $item->pph22_amount);
        $this->assertSame('SK-123/MK/2026 (terlampir di checklist dokumen)', $item->exemption_document_ref);
    }

    #[Test]
    public function approve_memblokir_pembebasan_tanpa_dokumen_pada_draf_lama(): void
    {
        // Draf lama (dibuat sebelum perbaikan) — pembebasan tanpa dokumen.
        $declaration = CustomsDeclaration::query()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'pib_number' => 'PIB-DRAFT-'.$this->job->job_number,
            'version' => 1,
            'status' => CustomsDeclaration::STATUS_DRAFT,
            'kmk_number' => 'KMK-TEST-16000',
            'created_by_user_id' => $this->customs->id,
        ]);
        $declaration->items()->create([
            'company_id' => $this->company->id,
            'item_number' => 1,
            'hs_code' => '8471302000',
            'currency_code' => 'USD',
            'is_pph22_exempt' => true,
            'exemption_reason' => 'Hibah bencana.',
            'exemption_document_ref' => null,
            'pph22_category' => 'bebas_pasal_4',
            'pph22_rate' => 0,
            'pph22_amount' => 0,
        ]);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', ['job' => $this->job->id, 'declaration' => $declaration->id])
        );

        $response->assertSessionHasErrors(['approval']);
        $this->assertSame(CustomsDeclaration::STATUS_DRAFT, $declaration->fresh()->status);
    }
}
