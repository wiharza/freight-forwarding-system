<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class RbacMatrixTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private function userWithRoles(string ...$slugs): User
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $user->roles()->sync(array_map(fn (string $slug): int => $this->roleId($slug), $slugs));
        $user->refresh();

        return $user;
    }

    #[Test]
    public function admin_hanya_punya_fitur_master_data_dan_user_management(): void
    {
        $admin = $this->userWithRoles('admin');

        // Admin BUKAN superuser — hanya fitur eksplisitnya (ADR 0006).
        $this->assertTrue(\Gate::forUser($admin)->allows('master_data'));
        $this->assertTrue(\Gate::forUser($admin)->allows('user_management'));

        $this->assertTrue(\Gate::forUser($admin)->denies('calculator_pib'));
        $this->assertTrue(\Gate::forUser($admin)->denies('invoice'));
        $this->assertTrue(\Gate::forUser($admin)->denies('input_milestone'));
        $this->assertTrue(\Gate::forUser($admin)->denies('verify_milestone'));
    }

    #[Test]
    public function cs_hanya_bisa_entri_job(): void
    {
        $cs = $this->userWithRoles('cs');

        $this->assertTrue(\Gate::forUser($cs)->allows('job_entry'));
        $this->assertTrue(\Gate::forUser($cs)->denies('calculator_pib'));
        $this->assertTrue(\Gate::forUser($cs)->denies('master_data'));
        $this->assertTrue(\Gate::forUser($cs)->denies('kurs_internal'));
    }

    #[Test]
    public function customs_specialist_memegang_cabang_pabean(): void
    {
        $cs = $this->userWithRoles('customs_specialist');

        $this->assertTrue(\Gate::forUser($cs)->allows('calculator_pib'));
        $this->assertTrue(\Gate::forUser($cs)->allows('approve_snapshot'));
        $this->assertTrue(\Gate::forUser($cs)->allows('override_fta'));
        $this->assertTrue(\Gate::forUser($cs)->denies('job_entry'));
        $this->assertTrue(\Gate::forUser($cs)->denies('user_management'));
    }

    #[Test]
    public function ops_memegang_penugasan_verifikasi_dan_trucking(): void
    {
        $ops = $this->userWithRoles('ops');

        $this->assertTrue(\Gate::forUser($ops)->allows('job_assignment'));
        $this->assertTrue(\Gate::forUser($ops)->allows('verify_milestone'));
        $this->assertTrue(\Gate::forUser($ops)->allows('trucking_surat_jalan'));
        $this->assertTrue(\Gate::forUser($ops)->denies('invoice'));
        $this->assertTrue(\Gate::forUser($ops)->denies('master_data'));
    }

    #[Test]
    public function field_hanya_bisa_input_milestone(): void
    {
        $field = $this->userWithRoles('field');

        $this->assertTrue(\Gate::forUser($field)->allows('input_milestone'));
        $this->assertTrue(\Gate::forUser($field)->denies('job_entry'));
        $this->assertTrue(\Gate::forUser($field)->denies('invoice'));
        $this->assertTrue(\Gate::forUser($field)->denies('verify_milestone'));
        $this->assertTrue(\Gate::forUser($field)->denies('master_data'));
    }

    #[Test]
    public function finance_memegang_kurs_invoice_pembayaran_dan_close(): void
    {
        $finance = $this->userWithRoles('finance');

        $this->assertTrue(\Gate::forUser($finance)->allows('kurs_internal'));
        $this->assertTrue(\Gate::forUser($finance)->allows('invoice'));
        $this->assertTrue(\Gate::forUser($finance)->allows('pembayaran_alokasi'));
        $this->assertTrue(\Gate::forUser($finance)->allows('reopen_close'));
        $this->assertTrue(\Gate::forUser($finance)->allows('laporan_keuangan'));
        $this->assertTrue(\Gate::forUser($finance)->denies('calculator_pib'));
        $this->assertTrue(\Gate::forUser($finance)->denies('master_data'));
    }

    #[Test]
    public function peran_jamak_menggabungkan_izin_tetap_tidak_melanggar_matriks(): void
    {
        // Satu orang pegang CS + Customs Specialist — union izinnya.
        $multi = $this->userWithRoles('cs', 'customs_specialist');

        $this->assertTrue(\Gate::forUser($multi)->allows('job_entry'));
        $this->assertTrue(\Gate::forUser($multi)->allows('calculator_pib'));
        $this->assertTrue(\Gate::forUser($multi)->denies('invoice')); // tidak karena jamak jadi dapat semua
    }
}
