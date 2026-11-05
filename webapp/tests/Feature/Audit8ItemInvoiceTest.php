<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\InvoiceNumberCounter;
use App\Models\Job;
use App\Models\ShippingLine;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi temuan audit 8 item (2026-11-05), item 4 — invoice
 * bersamaan: store() mengunci baris Job di awal transaksi + nomor
 * diambil dari counter per tahun yang dikunci, bukan `max + 1`.
 *
 * Catatan jujur: konkurensi paralel sejati (dua request HTTP bersamaan)
 * TIDAK bisa dibuktikan di harness ini — PHPUnit berjalan di SQLite
 * :memory: satu koneksi, dan `lockForUpdate` butuh dua koneksi
 * PostgreSQL. Yang dibuktikan test di sini: (a) penomoran berurutan dari
 * counter, (b) guard sekuensial Blok A/C tetap bekerja lewat store(),
 * (c) baris counter bertambah. Serialisasi paralelnya bersandar pada
 * `lockForUpdate` (Job + counter) dan harus dibuktikan di VPS/Postgres.
 */
class Audit8ItemInvoiceTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $finance;

    private Importir $importir;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);
    }

    #[Test]
    public function nomor_invoice_berurutan_dari_counter_per_tahun(): void
    {
        $year = date('Y');

        $job2 = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $this->job->shipping_line_id,
        ]);

        $this->buatInvoice($this->job, 1000000);
        $this->buatInvoice($job2, 1000000);

        $this->assertDatabaseHas('invoices', ['job_id' => $this->job->id, 'invoice_number' => "INV-{$year}-000001"]);
        $this->assertDatabaseHas('invoices', ['job_id' => $job2->id, 'invoice_number' => "INV-{$year}-000002"]);
        $this->assertSame(3, (int) InvoiceNumberCounter::query()->where('year', $year)->value('next_sequence'));
    }

    #[Test]
    public function invoice_kedua_job_yang_sama_tidak_menagih_ulang_jasa(): void
    {
        $this->buatInvoice($this->job, 5000000);

        // Invoice kedua dengan jasa > 0 → 422 (Blok C sudah ditagih).
        $response = $this->actingAs($this->finance)->post(route('finance.invoices.store', ['job' => $this->job->id]), [
            'invoice_date' => '2026-11-02',
            'blok_c_service_fee' => 5000000,
            'blok_c_ppn_rate' => 0.1200,
        ]);
        $response->assertSessionHasErrors('blok_c_service_fee');

        // Invoice kedua dengan jasa 0 → boleh (susulan Blok B), nomor lanjut.
        $year = date('Y');
        $response = $this->actingAs($this->finance)->post(route('finance.invoices.store', ['job' => $this->job->id]), [
            'invoice_date' => '2026-11-02',
            'blok_c_service_fee' => 0,
            'blok_c_ppn_rate' => 0.1200,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', ['job_id' => $this->job->id, 'invoice_number' => "INV-{$year}-000002"]);
    }

    private function buatInvoice(Job $job, int $serviceFee): void
    {
        $response = $this->actingAs($this->finance)->post(route('finance.invoices.store', ['job' => $job->id]), [
            'invoice_date' => '2026-11-01',
            'due_date' => '2026-11-15',
            'blok_c_service_fee' => $serviceFee,
            'blok_c_ppn_rate' => 0.1200,
        ]);
        $response->assertSessionHasNoErrors();
        // Redirect ke halaman invoice — gagal bila store 500 (mis. nomor
        // bertabrakan), yang tidak tertangkap assertSessionHasNoErrors.
        $response->assertRedirect();
    }
}
