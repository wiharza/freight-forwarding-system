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
use Database\Seeders\LegalTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes reproduksi + regresi temuan audit 8 item (2026-11-05), item 3 —
 * kalkulasi pajak PIB:
 *
 * (a) Form mengirim is_ppn_luxury=false secara default dan service
 *     mengutamakan nilai itu daripada klasifikasi HS (daftar barang mewah).
 *     Klasifikasi mewah WAJIB dari daftar master HS (Q29), input klien
 *     diabaikan. Dasar hukum: PMK 11/2025 jo. PMK 53/2025 — DPP 11/12
 *     hanya untuk barang NON-mewah; barang mewah DPP penuh Nilai Impor.
 * (b) Ambang PPnBM memakai CIF sebelum Bea Masuk — padahal PP 73/2019
 *     menetapkan ambang Nilai Impor (NI): kendaraan NI > USD 30.000
 *     (PV-6, riset §3). Dasar PPnBM pun Nilai Impor (UU PPN Ps 16D).
 * (c) Lelang: basis sudah diperbaiki PR #13 (7,5% dari harga jual lelang)
 *     — verifikasi ulang apakah ada jalur yang masih memakai basis lama,
 *     khususnya approve draf LAMA tanpa harga lelang.
 * (d) Pembebasan Pasal 4: sudah diperbaiki PR #13 (alasan + dokumen wajib
 *     di storeDraft + approve) — verifikasi ulang via suite yang sudah ada.
 *
 * Fixture HS 8703.23.51 (LegalTariffSeeder): barang mewah PPN + PPnBM 20%
 * dengan ambang NI USD 30.000; tarif MFN 50% (terverifikasi).
 */
class Audit8ItemPibTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $customs;

    private User $customs2;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->customs = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs->roles()->sync([$this->roleId('customs_specialist')]);
        $this->customs2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->customs2->roles()->sync([$this->roleId('customs_specialist')]);

        $importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);
        $pol = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);
        $pod = Location::factory()->create(['company_id' => $this->company->id, 'type' => Location::TYPE_PORT]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $importir->id,
            'shipping_line_id' => $line->id,
            'pol_id' => $pol->id,
            'pod_id' => $pod->id,
        ]);

        TaxExchangeRate::query()->create([
            'company_id' => $this->company->id,
            'kmk_number' => 'KMK-TEST-16000',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'currency_code' => 'USD',
            'rate_to_idr' => '16000',
            'source' => 'test',
        ]);

        $this->seed(LegalTariffSeeder::class);
    }

    private function item8703(float $fobUsd, bool $luxuryFlag = false): array
    {
        return [
            'hs_code' => '8703.23.51',
            'description' => 'Mobil penumpang sedan',
            'currency_code' => 'USD',
            'fob_valas' => $fobUsd,
            'freight_valas' => 0,
            'insurance_valas' => 0,
            'insurance_basis' => 'input_riil',
            'is_ppn_luxury' => $luxuryFlag,
        ];
    }

    #[Test]
    public function hs_mewah_dengan_flag_false_tetap_dihitung_mewah(): void
    {
        // CIF USD 25.000 → CIF Rp 400jt → BM 50% Rp 200jt → NI Rp 600jt.
        // HS 8703.23.51 ada di daftar barang mewah PPN: DPP PPN harus NI
        // penuh (12% × 600jt = 72jt), bukan 11/12 (66jt).
        $res = (new PibCalculator)->calculateItem($this->item8703(25000, false));

        $this->assertTrue($res['is_ppn_luxury']);
        $this->assertEquals(600000000.0, $res['nilai_impor']);
        $this->assertEquals(600000000.0, $res['dpp_ppn']);
        $this->assertEquals(72000000.0, $res['ppn_amount']);
    }

    #[Test]
    public function ambang_ppnbm_memakai_nilai_impor_bukan_cif(): void
    {
        // CIF USD 25.000 (di bawah ambang 30rb) tetapi NI Rp 600jt ≈
        // USD 37.500 (di atas ambang) — PPnBM 20% WAJIB kena:
        // 20% × 600jt = 120jt.
        $res = (new PibCalculator)->calculateItem($this->item8703(25000, true));

        $this->assertEquals(0.20, $res['ppnbm_rate']);
        $this->assertEquals(120000000.0, $res['ppnbm_amount']);
    }

    #[Test]
    public function ambang_ppnbm_tidak_kena_bila_nilai_impor_di_bawah_ambang(): void
    {
        // CIF USD 10.000 → NI Rp 240jt ≈ USD 15.000 < 30rb → PPnBM 0.
        $res = (new PibCalculator)->calculateItem($this->item8703(10000, true));

        $this->assertEquals(0.0, $res['ppnbm_rate']);
        $this->assertEquals(0.0, $res['ppnbm_amount']);
    }

    #[Test]
    public function approve_memblokir_draf_lama_lelang_tanpa_harga_lelang(): void
    {
        // Draf dibuat lewat storeDraft (valid), lalu diubah langsung di DB
        // menjadi lelang tanpa harga — simulasi draf lama sebelum migrasi
        // 001800. Approve harus menolak, seperti halnya pembebasan tanpa
        // alasan/dokumen pada draf lama.
        $response = $this->actingAs($this->customs)->post(
            route('jobs.calculator.draft', $this->job),
            [
                'incoterm' => 'FOB',
                'items' => [[
                    'hs_code' => '8703.23.51',
                    'currency_code' => 'USD',
                    'fob_valas' => 25000,
                    'freight_valas' => 0,
                    'insurance_basis' => 'input_riil',
                    'insurance_valas' => 0,
                ]],
            ],
        );
        $response->assertSessionHasNoErrors();

        $declaration = CustomsDeclaration::query()
            ->where('job_id', $this->job->id)->latest('version')->firstOrFail();
        $declaration->items()->update([
            'is_auction' => true,
            'auction_price_idr' => null,
            'pph22_category' => 'lelang_7.5',
        ]);

        $response = $this->actingAs($this->customs2)->post(
            route('jobs.calculator.approve', [$this->job, $declaration]),
        );

        $response->assertSessionHasErrors('approval');
        $this->assertSame(
            CustomsDeclaration::STATUS_DRAFT,
            $declaration->fresh()->status,
        );
    }
}
