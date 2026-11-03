<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceApprovalLimit;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        $invoices = Invoice::query()
            ->with(['job:id,job_number', 'importir:id,name', 'creator:id,name', 'approver:id,name'])
            ->orderByDesc('invoice_number')
            ->paginate(20);

        return Inertia::render('finance/invoices', [
            'invoices' => $invoices,
        ]);
    }

    public function create(Job $job): Response
    {
        Gate::authorize('invoice');

        $job->load(['importir', 'shippingLine', 'costs' => fn ($q) => $q->where('is_billable', true)]);
        $job->load(['costs' => fn ($q) => $q->where('payer_flag', '!=', 'importir_langsung')]);

        return Inertia::render('finance/invoice-create', [
            'job' => $job,
            'billableCosts' => $job->costs->filter(fn ($c) => $c->isBillable()),
        ]);
    }

    public function store(Request $request, Job $job): RedirectResponse
    {
        Gate::authorize('invoice');

        $data = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'blok_c_service_fee' => ['required', 'numeric', 'min:0'],
            'blok_c_ppn_rate' => ['required', 'numeric', 'min:0', 'max:0.13'],
            'faktur_pajak_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $importir = $job->importir;
        $snapshot = \App\Models\CustomsDeclaration::query()
            ->where('job_id', $job->id)
            ->where('status', '!=', 'draft')
            ->latest('version')
            ->first();

        // Blok (a) — dari Snapshot SPT PIB (ADR 0004), bukan dari Kalkulator
        $blokA = [
            'bm' => (float) ($snapshot->total_bm ?? 0),
            'ppn_impor' => (float) ($snapshot->total_ppn ?? 0),
            'pph22' => (float) ($snapshot->total_pph22 ?? 0),
            'ppnbm' => (float) ($snapshot->total_ppnbm ?? 0),
            'pungutan_lain' => (float) ($snapshot->total_pungutan_lain ?? 0),
        ];
        $blokATotal = $blokA['bm'] + $blokA['ppn_impor'] + $blokA['pph22'] + $blokA['ppnbm'] + $blokA['pungutan_lain'];

        // Blok (b) — biaya pihak ketiga (pass-through tanpa markup)
        $billableCosts = $job->costs()
            ->where('payer_flag', '!=', 'importir_langsung')
            ->where('cost_type', '!=', 'container_deposit_forfeit')
            ->where('cost_type', '!=', 'ocean_freight') // hanya biaya selain freight
            ->where('status', 'aktual')
            ->get();

        $blokBTotal = 0.0;
        $blokBPPN = 0.0;

        // Blok (c) — Jasa PPJK + PPN 12% × DPP 11/12
        $serviceFee = (float) $data['blok_c_service_fee'];
        $dppLain = round($serviceFee * (11 / 12), 4);
        $ppnRate = (float) $data['blok_c_ppn_rate'];
        $ppnJasa = round($dppLain * $ppnRate, 4);

        $totalInvoice = $blokATotal + $blokBTotal + $serviceFee + $ppnJasa;

        // Generate nomor invoice berurutan
        $lastInvoice = Invoice::query()->orderByDesc('id')->first();
        $nextNumber = $lastInvoice ? (int) filter_var($lastInvoice->invoice_number, FILTER_SANITIZE_NUMBER_INT) + 1 : 1;
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

        $invoice = Invoice::query()->create([
            'company_id' => $request->user()->company_id,
            'job_id' => $job->id,
            'importir_id' => $importir->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? now()->addDays($importir->payment_terms_days ?? 14)->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'blok_a_bm' => $blokA['bm'],
            'blok_a_ppn_impor' => $blokA['ppn_impor'],
            'blok_a_pph22' => $blokA['pph22'],
            'blok_a_ppnbm' => $blokA['ppnbm'],
            'blok_a_pungutan_lain' => $blokA['pungutan_lain'],
            'blok_a_total' => $blokATotal,
            'snapshot_pib_id' => $snapshot?->id,
            'blok_b_total' => $blokBTotal,
            'blok_b_ppn' => $blokBPPN,
            'blok_c_service_fee' => $serviceFee,
            'blok_c_dpp_lain' => $dppLain,
            'blok_c_ppn_rate' => $ppnRate,
            'blok_c_ppn' => $ppnJasa,
            'total_invoice' => $totalInvoice,
            'created_by_user_id' => $request->user()->id,
            'faktur_pajak_number' => $data['faktur_pajak_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Simpan invoice items dari billable costs
        foreach ($billableCosts as $cost) {
            InvoiceItem::query()->create([
                'company_id' => $request->user()->company_id,
                'invoice_id' => $invoice->id,
                'job_cost_id' => $cost->id,
                'blok' => 'b',
                'description' => $cost->cost_type,
                'currency_code' => $cost->currency_code,
                'amount_valas' => $cost->amount_valas,
                'kurs' => $cost->kurs_internal_rate,
                'dpp' => $cost->amount_idr,
                'ppn_rate' => 0,
                'ppn_amount' => 0,
                'total_line' => $cost->amount_idr,
                'kena_ppn_jasa' => false,
            ]);
        }

        // Blok (c) — Jasa PPJK
        InvoiceItem::query()->create([
            'company_id' => $request->user()->company_id,
            'invoice_id' => $invoice->id,
            'blok' => 'c',
            'description' => 'Jasa PPJK (Service Fee)',
            'currency_code' => 'IDR',
            'amount_valas' => 0,
            'kurs' => 1,
            'dpp' => $dppLain,
            'ppn_rate' => $ppnRate,
            'ppn_amount' => $ppnJasa,
            'total_line' => $serviceFee + $ppnJasa,
            'kena_ppn_jasa' => true,
        ]);

        Audit::record('invoice.created', $invoice, ['invoice_number' => $invoiceNumber], 'Pembuatan draf invoice');

        return redirect()->route('finance.invoices.show', $invoice->id);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load(['job:id,job_number', 'importir:id,name', 'items', 'creator:id,name', 'approver:id,name']);

        return Inertia::render('finance/invoice-show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Approve invoice — Maker-Checker + Batas Approve di luar Blok Pungutan Negara (ADR 0007).
     */
    public function approve(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoice');

        $fakturPajak = $request->input('faktur_pajak_number');
        $invoice->approve($request->user(), $fakturPajak);

        return redirect()->back();
    }

    /**
     * Catat pembayaran (v1: tanpa auto-match bank, tanpa denda telat).
     */
    public function storePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('pembayaran_alokasi');

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = Payment::query()->create([
            'company_id' => $request->user()->company_id,
            'invoice_id' => $invoice->id,
            'importir_id' => $invoice->importir_id,
            'payment_date' => $data['payment_date'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'is_unallocated' => true,
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ]);

        Audit::record('payment.recorded', $payment, $data);

        return redirect()->back();
    }

    /**
     * Alokasi pembayaran 2-langkah (§6.3, Q22) — manual per baris dengan saran urutan.
     */
    public function allocate(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('pembayaran_alokasi');

        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_item_id' => ['nullable', 'exists:invoice_items,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
            'allocations.*.blok' => ['required', Rule::in(['a', 'b', 'c'])],
            'allocations.*.reason' => ['nullable', 'string'],
        ]);

        $invoice = Invoice::query()->findOrFail($data['invoice_id']);

        DB::beginTransaction();
        try {
            $totalAllocated = 0;

            foreach ($data['allocations'] as $alloc) {
                // Batas: alokasi per baris tidak boleh melebihi sisa tagihan baris
                if (isset($alloc['invoice_item_id'])) {
                    $item = InvoiceItem::query()->findOrFail($alloc['invoice_item_id']);
                    $sisaBaris = (float) $item->total_line - $item->allocations()->sum('amount');
                    if ($alloc['amount'] > $sisaBaris) {
                        throw ValidationException::withMessages([
                            'allocations' => "Alokasi melebihi sisa tagihan baris: {$item->description}",
                        ]);
                    }
                }

                PaymentAllocation::query()->create([
                    'company_id' => $request->user()->company_id,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $alloc['invoice_item_id'] ?? null,
                    'job_id' => $invoice->job_id,
                    'amount' => $alloc['amount'],
                    'blok' => $alloc['blok'],
                    'reason' => $alloc['reason'] ?? null,
                    'allocated_by_user_id' => $request->user()->id,
                ]);

                $totalAllocated += $alloc['amount'];
            }

            // Batas: total alokasi tidak boleh melebihi jumlah pembayaran
            if ($totalAllocated > (float) $payment->amount) {
                throw ValidationException::withMessages([
                    'allocations' => 'Total alokasi melebihi jumlah pembayaran.',
                ]);
            }

            // Update invoice total_paid
            $invoice->total_paid = (float) $invoice->total_paid + $totalAllocated;
            $invoice->save();

            // Cek lunas
            if ($invoice->sisaTagihan() <= 0) {
                $invoice->update(['status' => Invoice::STATUS_LOCKED]);
            }

            Audit::record('payment.allocated', $payment, [
                'invoice_id' => $invoice->id,
                'total_allocated' => $totalAllocated,
            ], 'Alokasi pembayaran ke baris invoice');

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->back();
    }

    /**
     * Eksposur per Importir (§7b, Q18) — eksposur bruto + kolom Titipan Dana terpisah.
     */
    public function exposure(): Response
    {
        $importirs = Importir::query()->get();

        $exposures = $importirs->map(function ($imp) {
            $totalRealCost = JobCost::query()
                ->whereHas('job', fn ($q) => $q->where('importir_id', $imp->id))
                ->where('status', 'aktual')
                ->where('payer_flag', '!=', 'importir_langsung')
                ->sum('amount_idr');

            $totalAllocated = PaymentAllocation::query()
                ->whereHas('job', fn ($q) => $q->where('importir_id', $imp->id))
                ->sum('amount');

            $titipanDana = TitipanDana::query()
                ->where('importir_id', $imp->id)
                ->get()
                ->sum(fn ($t) => $t->sisaSaldo());

            return [
                'importir_id' => $imp->id,
                'importir_name' => $imp->name,
                'exposure_bruto' => $totalRealCost - $totalAllocated,
                'titipan_dana_saldo' => $titipanDana,
            ];
        });

        return Inertia::render('finance/exposure', [
            'exposures' => $exposures,
        ]);
    }
}