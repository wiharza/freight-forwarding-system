<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FtaTariff;
use App\Models\MfnTariff;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi temuan audit 8 item (2026-11-05), item 2 — persetujuan tarif
 * legal: endpoint create MFN/FTA masih menerima status `terverifikasi` dari
 * request, sehingga maker-checker (ADR 0006) bisa dilewati walau approval
 * Snapshot sudah diperketat. Status awal WAJIB `perlu_verifikasi` (di-paksa
 * server-side) dan verifikasi hanya oleh pengguna LAIN dari pembuatnya.
 */
class Audit8ItemTariffTest extends TestCase
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
        app(CompanyContext::class)->set($this->company->id);

        $this->admin1 = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin1->roles()->sync([$this->roleId('admin')]);
        $this->admin2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin2->roles()->sync([$this->roleId('admin')]);
    }

    #[Test]
    public function buat_mfn_dengan_status_terverifikasi_disimpan_perlu_verifikasi(): void
    {
        $response = $this->actingAs($this->admin1)->post(route('admin.master.mfn-tariffs.store'), [
            'hs_code' => '8471.30.20',
            'description' => 'Komputer laptop',
            'bm_rate' => 0.05,
            'effective_from' => '2026-01-01',
            'verification_status' => MfnTariff::STATUS_VERIFIED,
            'legal_basis' => 'PMK 62/2025',
        ]);

        $response->assertSessionHasNoErrors();
        $tariff = MfnTariff::query()->firstOrFail();
        $this->assertSame(MfnTariff::STATUS_UNVERIFIED, $tariff->verification_status);
        $this->assertNull($tariff->approved_by_user_id);
    }

    #[Test]
    public function buat_fta_dengan_status_terverifikasi_disimpan_perlu_verifikasi(): void
    {
        $response = $this->actingAs($this->admin1)->post(route('admin.master.fta-tariffs.store'), [
            'scheme_code' => 'AKFTA',
            'hs_code' => '8471.30.20',
            'fta_rate' => 0.0,
            'effective_from' => '2026-01-01',
            'verification_status' => FtaTariff::STATUS_VERIFIED,
            'legal_basis' => 'PMK 88/2023',
        ]);

        $response->assertSessionHasNoErrors();
        $tariff = FtaTariff::query()->firstOrFail();
        $this->assertSame(FtaTariff::STATUS_UNVERIFIED, $tariff->verification_status);
        $this->assertNull($tariff->approved_by_user_id);
    }

    #[Test]
    public function verifikasi_oleh_pembuat_sendiri_ditolak_lewat_endpoint(): void
    {
        $tariff = MfnTariff::factory()->create([
            'company_id' => $this->company->id,
            'hs_code' => '8471.30.20',
            'verification_status' => MfnTariff::STATUS_UNVERIFIED,
            'created_by_user_id' => $this->admin1->id,
        ]);

        $response = $this->actingAs($this->admin1)->post(
            route('admin.master.mfn-tariffs.approve', $tariff),
            ['legal_basis' => 'PMK 62/2025'],
        );

        $response->assertSessionHasErrors('maker_checker');
        $this->assertSame(MfnTariff::STATUS_UNVERIFIED, $tariff->fresh()->verification_status);
    }

    #[Test]
    public function verifikasi_oleh_admin_lain_berhasil_lewat_endpoint(): void
    {
        $tariff = MfnTariff::factory()->create([
            'company_id' => $this->company->id,
            'hs_code' => '8471.30.20',
            'verification_status' => MfnTariff::STATUS_UNVERIFIED,
            'created_by_user_id' => $this->admin1->id,
        ]);

        $response = $this->actingAs($this->admin2)->post(
            route('admin.master.mfn-tariffs.approve', $tariff),
            ['legal_basis' => 'PMK 62/2025'],
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(MfnTariff::STATUS_VERIFIED, $tariff->fresh()->verification_status);
        $this->assertSame($this->admin2->id, $tariff->fresh()->approved_by_user_id);
    }
}
