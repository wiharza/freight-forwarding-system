<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FtaTariff;
use App\Models\LuxuryGood;
use App\Models\MfnTariff;
use App\Models\Pph22Rule;
use App\Models\User;
use Database\Seeders\LegalTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class LegalTariffTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;
    private User $admin1;
    private User $admin2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();

        $this->admin1 = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin1->roles()->sync([$this->roleId('admin')]);

        $this->admin2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin2->roles()->sync([$this->roleId('admin')]);

        // Jalankan LegalTariffSeeder
        app(\App\Support\CompanyContext::class)->set($this->company->id);
        $this->seed(LegalTariffSeeder::class);
    }

    #[Test]
    public function seed_pph22_memuat_215_pos_hs_dan_prefix_matching_berfungsi(): void
    {
        // 214 pos tarif resmi hasil ekstraksi JDIH PMK 51/2025 (215 baris termasuk header)
        $this->assertGreaterThanOrEqual(214, Pph22Rule::query()->count());

        // Uji match pos 33.04 (Kosmetik - Lampiran A 10%)
        $rule = Pph22Rule::matchHs('3304.99.90');
        $this->assertNotNull($rule);
        $this->assertEquals('A', $rule->lampiran);
        $this->assertEquals('0.1000', (string) $rule->rate);

        // Uji match pos 84.71 (Komputer - ada di Lampiran A 10%)
        $laptop = Pph22Rule::matchHs('8471.30.20');
        $this->assertNotNull($laptop);
        $this->assertEquals('A', $laptop->lampiran);

        // Uji barang umum non-lampiran A/B/C (e.g. 0101.21.00 Hewan Hidup)
        $general = Pph22Rule::matchHs('0101.21.00');
        $this->assertNull($general);
    }

    #[Test]
    public function persetujuan_tarif_mfn_menegakkan_maker_checker(): void
    {
        $tariff = MfnTariff::factory()->create([
            'company_id' => $this->company->id,
            'hs_code' => '8471.30.20',
            'verification_status' => MfnTariff::STATUS_UNVERIFIED,
            'created_by_user_id' => $this->admin1->id,
        ]);

        // Admin1 (pembuat) coba approve sendiri -> WAJIB GAGAL (ADR 0006)
        $this->expectException(ValidationException::class);
        $tariff->approve($this->admin1, 'PMK 62/2025');
    }

    #[Test]
    public function admin_lain_bisa_memverifikasi_tarif_mfn_dengan_dasar_hukum(): void
    {
        $tariff = MfnTariff::factory()->create([
            'company_id' => $this->company->id,
            'hs_code' => '8471.30.20',
            'verification_status' => MfnTariff::STATUS_UNVERIFIED,
            'created_by_user_id' => $this->admin1->id,
        ]);

        $tariff->approve($this->admin2, 'PMK 62/2025 Lampiran I');
        $tariff->refresh();

        $this->assertEquals(MfnTariff::STATUS_VERIFIED, $tariff->verification_status);
        $this->assertEquals($this->admin2->id, $tariff->approved_by_user_id);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'mfn_tariff.approved',
            'object_id' => $tariff->id,
        ]);
    }

    #[Test]
    public function master_fta_hanya_menerima_9_skema_resmi_aktif(): void
    {
        $this->assertArrayHasKey('ACFTA', FtaTariff::SCHEMES);
        $this->assertArrayHasKey('IA-CEPA', FtaTariff::SCHEMES);
        $this->assertArrayNotHasKey('IEU-CEPA', FtaTariff::SCHEMES); // belum berlaku per Nov 2026 (§3.5)

        $response = $this->actingAs($this->admin1)->post(route('admin.master.fta-tariffs.store'), [
            'scheme_code' => 'IEU-CEPA',
            'hs_code' => '8471.30.20',
            'fta_rate' => '0.00',
            'effective_from' => '2026-11-01',
            'verification_status' => 'terverifikasi',
            'legal_basis' => 'Perjanjian',
        ]);

        $response->assertSessionHasErrors(['scheme_code']);
    }

    #[Test]
    public function luxury_goods_mendeteksi_ambang_nilai_impor_ppnbm(): void
    {
        // Kendaraan bermotor (HS 8703) ambang USD 30.000 (PV-6, PP 73/2019)
        // Kasus 1: Nilai Impor USD 25.000 (di bawah ambang)
        $below = LuxuryGood::checkHs('8703.23.51', 25000);
        $this->assertTrue($below['is_ppn_luxury']); // PPN tetap 12% penuh
        $this->assertFalse($below['has_ppnbm']);    // PPnBM belum kena karena <= 30k

        // Kasus 2: Nilai Impor USD 45.000 (di atas ambang)
        $above = LuxuryGood::checkHs('8703.23.51', 45000);
        $this->assertTrue($above['is_ppn_luxury']);
        $this->assertTrue($above['has_ppnbm']);
        $this->assertEquals(0.20, $above['ppnbm_rate']); // 20%
    }
}
