<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\JobDocument;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ShippingLine;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Test regresi untuk 3 bug yang ditemukan saat demo dengan data contoh
 * (tidak tertangkap 136 test lama karena path-nya tidak diuji):
 *
 *  BUG-1: total invoice tidak memasukkan Blok (b) biaya billable
 *  BUG-2: alokasi pembayaran kumulatif bisa melebihi nominal payment
 *  BUG-3: upload dokumen lintas-Job (URL Job A + ID dokumen Job B) menimpa dokumen B
 */
class BugfixRegressionTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $finance;

    private User $cs;

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

        $this->cs = User::factory()->create(['company_id' => $this->company->id]);
        $this->cs->roles()->sync([$this->roleId('cs')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);
    }

    // ------------------------------------------------------------------
    // BUG-1: Blok (b) harus masuk total invoice
    // ------------------------------------------------------------------

    #[Test]
    public function total_invoice_memasukkan_blok_b_biaya_billable(): void
    {
        // Biaya aktual yang dapat ditagih Rp 500.000
        JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => JobCost::TYPE_THC,
            'payer_flag' => JobCost::PAYER_PPJK,
            'currency_code' => 'IDR',
            'amount_valas' => 500000,
            'kurs_internal_rate' => 1,
            'amount_idr' => 500000,
            'status' => JobCost::STATUS_AKTUAL,
            'created_by_user_id' => $this->cs->id,
        ]);

        $response = $this->actingAs($this->finance)->post(route('finance.invoices.store', ['job' => $this->job->id]), [
            'invoice_date' => '2026-11-04',
            'blok_c_service_fee' => 100000, // jasa Rp 100.000
            'blok_c_ppn_rate' => '0.12',
        ]);
        $response->assertSessionHasNoErrors();

        $invoice = Invoice::query()->where('job_id', $this->job->id)->firstOrFail();

        // Blok B = 500.000; Blok C = 100.000 + PPN (100.000 × 11/12 × 0.12 = 11.000)
        $this->assertSame('500000.0000', (string) $invoice->blok_b_total, 'Blok B harus Rp 500.000');
        $this->assertSame('100000.0000', (string) $invoice->blok_c_service_fee);
        $this->assertSame('11000.0000', (string) $invoice->blok_c_ppn, 'PPN jasa 12% × DPP 11/12 × 100.000 = 11.000');

        // Total = 0 (blok A, belum ada snapshot) + 500.000 + 100.000 + 11.000 = 611.000
        $this->assertSame('611000.0000', (string) $invoice->total_invoice, 'Total invoice harus mencakup Blok B');
    }

    // ------------------------------------------------------------------
    // BUG-2: Alokasi kumulatif tidak boleh melebihi nominal payment
    // ------------------------------------------------------------------

    #[Test]
    public function alokasi_kedua_melebihi_sisa_payment_ditolak(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'status' => Invoice::STATUS_APPROVED,
            'blok_a_total' => 0,
            'blok_b_total' => 500000,
            'blok_c_service_fee' => 100000,
            'blok_c_ppn' => 11000,
            'total_invoice' => 611000,
            'total_paid' => 0,
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'importir_id' => $this->importir->id,
            'payment_date' => '2026-11-04',
            'amount' => 100, // Rp 100
            'method' => 'transfer',
            'is_unallocated' => true,
            'created_by_user_id' => $this->finance->id,
        ]);

        // Alokasi pertama Rp 80 → OK (sisa 20)
        $first = $this->actingAs($this->finance)->post(route('finance.payments.allocate', $payment->id), [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => null, 'amount' => 80, 'blok' => 'b', 'reason' => 'sebagian'],
            ],
        ]);
        $first->assertSessionHasNoErrors();

        // Alokasi kedua Rp 80 → HARUS DITOLAK (80 > sisa 20)
        $second = $this->actingAs($this->finance)->post(route('finance.payments.allocate', $payment->id), [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => null, 'amount' => 80, 'blok' => 'b', 'reason' => 'coba lagi'],
            ],
        ]);
        $second->assertSessionHasErrors(['allocations']);

        // Verifikasi integritas: total teralokasi tetap 80, bukan 160
        $this->assertEquals(80, (float) PaymentAllocation::query()->where('payment_id', $payment->id)->sum('amount'));
        $invoice->refresh();
        $this->assertEquals(80, (float) $invoice->total_paid, 'total_paid tidak boleh menjadi 160');
    }

    // ------------------------------------------------------------------
    // BUG-3: Upload dokumen lintas-Job harus ditolak (404) & dokumen tidak berubah
    // ------------------------------------------------------------------

    #[Test]
    public function upload_dokumen_lintas_job_ditolak_dan_dokumen_asli_utuh(): void
    {
        Storage::fake('public');

        // Job A (URL) dan Job B (pemilik dokumen)
        $line = ShippingLine::query()->first();
        $jobA = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);
        $jobB = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);

        $docB = JobDocument::create([
            'company_id' => $this->company->id,
            'job_id' => $jobB->id,
            'phase' => 'pra_filing',
            'document_type' => JobDocument::TYPE_INVOICE,
            'status' => JobDocument::STATUS_BELUM_ADA,
            'notes' => 'catatan asli B',
        ]);

        // CS mencoba upload via URL Job A memakai ID dokumen Job B
        $response = $this->actingAs($this->cs)->post(
            route('jobs.documents.upload', ['job' => $jobA->id, 'document' => $docB->id]),
            [
                'file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
                'metadata' => ['note' => 'milik A'],
            ]
        );

        $response->assertNotFound(); // 404 — dokumen tidak ada di Job A

        // Dokumen B tidak boleh berubah
        $docB->refresh();
        $this->assertNull($docB->file_path);
        $this->assertSame(JobDocument::STATUS_BELUM_ADA, $docB->status);
        $this->assertSame('catatan asli B', $docB->notes);

        // Tidak ada file yang tersimpan di folder Job A
        $this->assertCount(0, Storage::disk('public')->allFiles("documents/{$this->company->id}/{$jobA->id}"));
    }

    #[Test]
    public function verify_dokumen_lintas_job_ditolak(): void
    {
        // User dengan gate verify_milestone (ops) agar melewati Gate dan menguji cek kepemilikan
        $ops = User::factory()->create(['company_id' => $this->company->id]);
        $ops->roles()->sync([$this->roleId('ops')]);

        $line = ShippingLine::query()->first();
        $jobA = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);
        $docB = JobDocument::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id, // milik job lain (bukan jobA)
            'phase' => 'pra_filing',
            'document_type' => JobDocument::TYPE_INVOICE,
            'status' => JobDocument::STATUS_DIUPLOAD,
        ]);

        $response = $this->actingAs($ops)->post(
            route('jobs.documents.verify', ['job' => $jobA->id, 'document' => $docB->id]),
            ['notes' => 'verifikasi palsu']
        );

        $response->assertNotFound();
        $docB->refresh();
        $this->assertSame(JobDocument::STATUS_DIUPLOAD, $docB->status, 'status dokumen B tidak boleh berubah');
        $this->assertNull($docB->verified_by_user_id);
    }
}
