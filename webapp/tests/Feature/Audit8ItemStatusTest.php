<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\ShippingLine;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tes regresi temuan audit 8 item (2026-11-05), item 8 — status
 * Ditagih/Lunas dari invoice. Keduanya ADALAH status yang dimaksud
 * spek §2.3 (rantai dikunci: ... → Gate-In Empty Selesai → Ditagih →
 * Lunas → Closed), tetapi `recomputeStatus()` tidak pernah
 * menghasilkannya. Implementasi: tahap keuangan baru berlaku SETELAH
 * Gate-In Empty Selesai (status = tahap terjauh yang dicapai; invoice
 * bisa dibuat sejak PIB Filed dan tidak boleh melompati tahap logistik).
 * `recomputeStatus()` dipanggil dari invoice store/approve/allocate.
 */
class Audit8ItemStatusTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    private Company $company;

    private User $finance;

    private User $ops;

    private Importir $importir;

    private Job $job;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->company = Company::factory()->create();
        app(CompanyContext::class)->set($this->company->id);

        $this->finance = User::factory()->create(['company_id' => $this->company->id]);
        $this->finance->roles()->sync([$this->roleId('finance')]);

        $this->ops = User::factory()->create(['company_id' => $this->company->id]);
        $this->ops->roles()->sync([$this->roleId('ops')]);

        $this->importir = Importir::factory()->create(['company_id' => $this->company->id]);
        $line = ShippingLine::factory()->create(['company_id' => $this->company->id]);

        $this->job = Job::factory()->create([
            'company_id' => $this->company->id,
            'importir_id' => $this->importir->id,
            'shipping_line_id' => $line->id,
        ]);
        $this->job->initializeChecklist();

        $this->container = $this->job->containers()->create([
            'company_id' => $this->company->id,
            'container_number' => 'MSKU1234565',
            'size' => "40'",
            'type' => 'High Cube',
        ]);
    }

    #[Test]
    public function status_ditagih_setelah_gate_in_selesai_dan_invoice_dibuat(): void
    {
        $this->catatGateInEmpty();
        $this->assertSame(Job::STATUS_GATE_IN_SELESAI, $this->job->fresh()->recomputeStatus());

        $this->buatInvoiceModel(total: 1000000, paid: 0);

        $this->assertSame(Job::STATUS_DITAGIH, $this->job->fresh()->recomputeStatus());
    }

    #[Test]
    public function status_lunas_setelah_invoice_lunas_penuh_lewat_alokasi(): void
    {
        $this->catatGateInEmpty();

        $invoice = $this->buatInvoiceModel(total: 1000000, paid: 0);
        $payment = $this->buatPayment($invoice, 1000000);

        $response = $this->actingAs($this->finance)->post(route('finance.payments.allocate', $payment->id), [
            'invoice_id' => $invoice->id,
            'allocations' => [
                ['invoice_item_id' => null, 'amount' => 1000000, 'blok' => 'a'],
            ],
        ]);
        $response->assertSessionHasNoErrors();

        // Alokasi memicu recomputeStatus — Job harus Lunas.
        $this->assertSame(Job::STATUS_LUNAS, $this->job->fresh()->cached_status);
    }

    #[Test]
    public function status_tidak_menjadi_ditagih_bila_invoice_dibuat_sebelum_gate_in(): void
    {
        // Rantai §2.3 dikunci: Ditagih/Lunas baru berlaku SETELAH Gate-In
        // Empty Selesai. Invoice yang dibuat saat Job masih Draft tidak
        // boleh melompati tahap logistik di status progres.
        $this->buatInvoiceModel(total: 1000000, paid: 0);

        $this->assertSame(Job::STATUS_DRAFT, $this->job->fresh()->recomputeStatus());
    }

    #[Test]
    public function status_tetap_gate_in_selesai_tanpa_invoice(): void
    {
        $this->catatGateInEmpty();

        $this->assertSame(Job::STATUS_GATE_IN_SELESAI, $this->job->fresh()->recomputeStatus());
    }

    #[Test]
    public function status_kembali_ditagih_bila_invoice_baru_belum_lunas(): void
    {
        $this->catatGateInEmpty();

        $this->buatInvoiceModel(total: 1000000, paid: 1000000);
        $this->assertSame(Job::STATUS_LUNAS, $this->job->fresh()->recomputeStatus());

        // Invoice susulan (mis. Blok B baru) yang belum lunas → Ditagih.
        $this->buatInvoiceModel(total: 500000, paid: 0);
        $this->assertSame(Job::STATUS_DITAGIH, $this->job->fresh()->recomputeStatus());
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

    private function catatGateInEmpty(): void
    {
        Milestone::query()->create([
            'company_id' => $this->company->id,
            'job_id' => $this->job->id,
            'container_id' => $this->container->id,
            'branch' => Milestone::BRANCH_LOGISTIK,
            'milestone_type' => Milestone::TYPE_GATE_IN_EMPTY,
            'occurred_at' => now(),
            'recorded_at' => now(),
            'is_backdate' => false,
            'is_active' => true,
            'created_by_user_id' => $this->ops->id,
        ]);
    }
}
