<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\ShippingLine;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi temuan audit 8 item (2026-11-05), item 4 — invoice
 * bersamaan, setelah REBASE ke master 6645196.
 *
 * Perilaku yang diuji adalah perbaikan yang sudah masuk master
 * (commit 6645196), bukan pendekatan counter per tahun dari branch ini
 * (commit 0adadbe + e4a5c3a, dibuang saat rebase karena digantikan):
 *  - store() mengunci baris Job di awal transaksi (serialisasi per Job);
 *  - nomor invoice tetap `max + 1` global, tabrakan unique constraint
 *    ditangani retry transaksi maks 3x (bukan 500).
 *
 * Catatan jujur: konkurensi paralel sejati (dua request HTTP bersamaan)
 * TIDAK bisa dibuktikan di harness ini — PHPUnit berjalan di SQLite
 * :memory: satu koneksi. Yang dibuktikan test di sini:
 *  (a) penomoran berurutan via `max + 1`,
 *  (b) guard sekuensial Blok A/C tetap bekerja lewat store(),
 *  (c) loop retry benar-benar berjalan: tabrakan unique constraint
 *       yang dipicu di dalam transaksi pertama membuat store() mencoba
 *       ulang dan berhasil (bukan 500). Tabrakannya nyata (baris duplikat
 *       disisipkan lewat query mentah di event `creating`, lalu INSERT
 *       Eloquent menabrak unique index `invoice_number`) — yang tidak
 *       dibuktikan di sini hanyalah interleaving dua koneksi bersamaan;
 *       itu tetap ranah VPS/Postgres.
 */
class Audit8ItemInvoiceRetryTest extends TestCase
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
    public function nomor_invoice_berurutan_max_plus_satu(): void
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

    #[Test]
    public function tabrakan_nomor_invoice_dicoba_ulang_bukan_500(): void
    {
        $year = date('Y');
        $companyId = $this->company->id;
        $jobId = $this->job->id;
        $importirId = $this->importir->id;
        $financeId = $this->finance->id;

        // Sekali saja: saat Eloquent hendak INSERT invoice pertama di
        // dalam transaksi, sisipkan dulu baris mentah dengan nomor yang
        // SAMA dengan yang baru dihitung controller (max + 1 = 000001 di
        // DB kosong). INSERT Eloquent lalu menabrak unique index
        // `invoice_number` — persis tabrakan yang terjadi antar-Job di
        // produksi. Listener menjadi no-op pada percobaan ulang.
        $tabrakanTerjadi = false;
        Invoice::creating(function (Invoice $invoice) use (&$tabrakanTerjadi, $year, $companyId, $jobId, $importirId, $financeId) {
            if ($tabrakanTerjadi) {
                return;
            }
            $tabrakanTerjadi = true;

            $maxSequence = 0;
            foreach (Invoice::query()->where('invoice_number', 'like', "INV-{$year}-%")->pluck('invoice_number') as $existing) {
                if (preg_match('/(\d+)$/', $existing, $m)) {
                    $maxSequence = max($maxSequence, (int) $m[1]);
                }
            }
            $nomorBentrok = 'INV-'.$year.'-'.str_pad((string) ($maxSequence + 1), 6, '0', STR_PAD_LEFT);

            DB::table('invoices')->insert([
                'company_id' => $companyId,
                'job_id' => $jobId,
                'importir_id' => $importirId,
                'invoice_number' => $nomorBentrok,
                'invoice_date' => '2026-11-01',
                'status' => Invoice::STATUS_DRAFT,
                'total_invoice' => 0,
                'total_paid' => 0,
                'created_by_user_id' => $financeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->buatInvoice($this->job, 1000000);

        // Tabrakan benar-benar terjadi (bukan test yang lolos hampa),
        // store() mencoba ulang dan berhasil — satu invoice, tanpa 500.
        $this->assertTrue($tabrakanTerjadi, 'Listener tabrakan tidak pernah berjalan — test ini tidak membuktikan apa-apa.');
        $this->assertSame(1, Invoice::query()->count());
        $this->assertDatabaseHas('invoices', ['job_id' => $this->job->id, 'invoice_number' => "INV-{$year}-000001"]);
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
        // bertabrakan tanpa retry), yang tidak tertangkap assertSessionHasNoErrors.
        $response->assertRedirect();
    }
}
