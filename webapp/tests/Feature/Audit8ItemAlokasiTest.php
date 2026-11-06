<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Job;
use App\Models\Payment;
use App\Models\ShippingLine;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi temuan audit 8 item (2026-11-05), item 5 — alokasi
 * dibatasi sisa tagihan invoice (`sisaTagihan()`), bukan hanya sisa
 * pembayaran. Tanpa invoice_item_id, cek per-baris dilewati sehingga
 * alokasi bisa melampaui total invoice selama masih dalam nominal
 * payment (total_paid > total_invoice).
 */
class Audit8ItemAlokasiTest extends TestCase
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
    public function alokasi_tanpa_item_melebihi_sisa_tagihan_ditolak(): void
    {
        $invoice = $this->buatInvoiceModel(total: 10000000);
        $payment = $this->buatPayment($invoice, 15000000);

        // 12jt < payment 15jt, tetapi > sisa tagihan 10jt → WAJIB GAGAL.
        $response = $this->actingAs($this->finance)->post(route('finance.payments.allocate', $payment->id), [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => null, 'amount' => 12000000, 'blok' => 'a'],
            ],
        ]);

        $response->assertSessionHasErrors('allocations');
        $this->assertSame(0.0, (float) $invoice->fresh()->total_paid);
        $this->assertDatabaseMissing('payment_allocations', ['payment_id' => $payment->id]);
    }

    #[Test]
    public function alokasi_tanpa_item_tepat_pada_sisa_tagihan_berhasil(): void
    {
        $invoice = $this->buatInvoiceModel(total: 10000000);
        $payment = $this->buatPayment($invoice, 15000000);

        $response = $this->actingAs($this->finance)->post(route('finance.payments.allocate', $payment->id), [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => null, 'amount' => 10000000, 'blok' => 'a'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(10000000.0, (float) $invoice->fresh()->total_paid);
    }

    #[Test]
    public function alokasi_per_item_tetap_dibatasi_sisa_baris(): void
    {
        $invoice = $this->buatInvoiceModel(total: 10000000);
        $item = InvoiceItem::query()->create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'blok' => 'b',
            'description' => 'THC',
            'currency_code' => 'IDR',
            'total_line' => 3000000,
        ]);
        $payment = $this->buatPayment($invoice, 15000000);

        $response = $this->actingAs($this->finance)->post(route('finance.payments.allocate', $payment->id), [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => $item->id, 'amount' => 4000000, 'blok' => 'b'],
            ],
        ]);

        $response->assertSessionHasErrors('allocations');
        $this->assertSame(0.0, (float) $invoice->fresh()->total_paid);
    }

    private function buatInvoiceModel(int $total, int $paid = 0): Invoice
    {
        return Invoice::factory()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'importir_id' => $this->importir->id,
            'invoice_number' => 'INV-TEST-'.$this->job->id.'-'.$total.'-'.$paid.'-'.random_int(1000, 9999),
            'status' => Invoice::STATUS_LOCKED,
            'total_invoice' => $total,
            'total_paid' => $paid,
            'created_by_user_id' => $this->finance->id,
        ]);
    }

    private function buatPayment(Invoice $invoice, int $amount): Payment
    {
        return Payment::query()->create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'importir_id' => $this->importir->id,
            'payment_date' => '2026-11-10',
            'amount' => $amount,
            'method' => Payment::METHOD_TRANSFER,
            'is_unallocated' => true,
        ]);
    }
}
