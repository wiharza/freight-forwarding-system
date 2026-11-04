<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinanceApprovalLimit;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\Payment;
use App\Models\ShippingLine;
use App\Models\TitipanDana;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;
    private User $finance1;
    private User $finance2;
    private User $ops;
    private Importir $importir;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(\App\Support\CompanyContext::class)->set($this->company->id);

        $this->finance1 = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance1->roles()->sync([$this->roleId('finance')]);

        $this->finance2 = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance2->roles()->sync([$this->roleId('finance')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);
    }

    #[Test]
    public function finance_dapat_membuat_invoice_dari_ledger(): void
    {
        // Simpan biaya ke ledger
        JobCost::create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'cost_type' => JobCost::TYPE_THC,
            'payer_flag' => JobCost::PAYER_PPJK,
            'currency_code' => 'IDR',
            'amount_valas' => 2500000,
            'kurs_internal_rate' => 1,
            'amount_idr' => 2500000,
            'status' => JobCost::STATUS_AKTUAL,
            'created_by_user_id' => $this->ops->id,
        ]);

        $response = $this->actingAs($this->finance1)->post(route('finance.invoices.store', ['job' => $this->job->id]), [
            'invoice_date' => '2026-11-01',
            'due_date' => '2026-11-15',
            'blok_c_service_fee' => 5000000,
            'blok_c_ppn_rate' => 0.1200,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', [
            'job_id' => $this->job->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);
    }

    #[Test]
    public function invoice_dibuat_oleh_finance_tidak_bisa_self_approve(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'created_by_user_id' => $this->finance1->id,
            'status' => Invoice::STATUS_DRAFT,
            'blok_b_total' => 2500000,
            'blok_c_service_fee' => 5000000,
        ]);

        // Finance1 (pembuat) coba approve sendiri -> WAJIB GAGAL (ADR 0006)
        $this->expectException(ValidationException::class);
        $invoice->approve($this->finance1);
    }

    #[Test]
    public function batas_approve_menolak_melebihi_limit(): void
    {
        // Set batas approve Finance1 = Rp 5jt
        FinanceApprovalLimit::create([
            'company_id' => $this->company->id,
            'user_id' => $this->finance1->id,
            'limit_amount' => 5000000,
            'unlimited' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'created_by_user_id' => $this->ops->id,
            'status' => Invoice::STATUS_DRAFT,
            'blok_b_total' => 2500000,
            'blok_c_service_fee' => 5000000,
            'blok_c_ppn' => 458333.3333,
        ]);
        // approvableAmount = blok_b_total + blok_c_service_fee + blok_c_ppn = 7,958,333

        $this->expectException(ValidationException::class);
        $invoice->approve($this->finance1);
    }

    #[Test]
    public function direktur_tanpa_batas_bisa_approve_invoice_besar(): void
    {
        $direktur = User::factory()->create(['company_id' => $this->company->id]);
        $direktur->roles()->sync([$this->roleId('finance')]);

        FinanceApprovalLimit::create([
            'company_id' => $this->company->id,
            'user_id' => $direktur->id,
            'limit_amount' => 999999999999,
            'unlimited' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'created_by_user_id' => $this->finance1->id,
            'status' => Invoice::STATUS_DRAFT,
            'blok_b_total' => 25000000,
            'blok_c_service_fee' => 50000000,
            'blok_c_ppn' => 4583333,
        ]);

        // Direktur (bukan pembuat) dengan unlimited limit bisa approve
        $invoice->approve($direktur);
        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_LOCKED, $invoice->status);
    }

    #[Test]
    public function pembayaran_dapat_dicatat_dan_mengurangi_tagihan(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'status' => Invoice::STATUS_LOCKED,
            'total_invoice' => 10000000,
            'total_paid' => 0,
        ]);

        $response = $this->actingAs($this->finance1)->post(route('finance.invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-11-10',
            'amount' => 6000000,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'TRF-2026-001',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 6000000,
        ]);
    }

    #[Test]
    public function alokasi_menolak_melebihi_total_pembayaran(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'status' => Invoice::STATUS_LOCKED,
            'total_invoice' => 10000000,
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'importir_id' => $this->importir->id,
            'payment_date' => '2026-11-10',
            'amount' => 5000000,
            'method' => Payment::METHOD_TRANSFER,
            'is_unallocated' => true,
        ]);

        // Coba alokasi Rp 7jt padahal pembayaran hanya Rp 5jt -> WAJIB GAGAL
        $response = $this->actingAs($this->finance1)->post(route('finance.payments.allocate', $payment->id), [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => null, 'amount' => 7000000, 'blok' => 'a'],
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    #[Test]
    public function alokasi_sisa_tidak_dialokasi_jadi_titipan_dana(): void
    {
        // Q22: kelebihan bayar (invoice lunas penuh, pembayaran bersisa)
        // WAJIB menjadi satu baris Titipan Dana untuk Importir itu — bukan
        // disenyapkan, bukan dinetting ke invoice lain.
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'status' => Invoice::STATUS_LOCKED,
            'total_invoice' => 10000000,
            'total_paid' => 0,
        ]);

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'importir_id' => $this->importir->id,
            'payment_date' => '2026-11-10',
            'amount' => 15000000,
            'method' => Payment::METHOD_TRANSFER,
            'is_unallocated' => true,
        ]);

        $response = $this->actingAs($this->finance1)->post(route('finance.payments.allocate', $payment->id), [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => null, 'amount' => 10000000, 'blok' => 'a'],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('titipan_dana', [
            'reference' => 'OVERPAY-PAY-'.$payment->id,
            'importir_id' => $this->importir->id,
            'amount' => 5000000,
        ]);
        $this->assertEquals(1, TitipanDana::query()->where('reference', 'OVERPAY-PAY-'.$payment->id)->count());
        $this->assertFalse($payment->fresh()->is_unallocated);
        $this->assertEquals(Invoice::STATUS_LOCKED, $invoice->fresh()->status);
    }

    #[Test]
    public function invoice_nomor_berurutan_tanpa_lompatan(): void
    {
        $invoice1 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'invoice_number' => 'INV-2026-000001',
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'invoice_number' => 'INV-2026-000002',
        ]);

        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-2026-000001']);
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-2026-000002']);
    }
}