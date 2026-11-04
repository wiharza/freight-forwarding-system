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
use App\Models\TitipanDana;
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
            // Untuk pemilih job saat membuat invoice baru
            'jobs' => Job::query()->with('importir:id,name')->orderByDesc('id')->get(['id', 'job_number', 'importir_id']),
        ]);
    }

    public function create(Job $job): Response
    {
        Gate::authorize('invoice');

        // BUGFIX: kolom `is_billable` tidak ada di skema job_costs — query ini
        // menggagalkan halaman di PostgreSQL. Kelayakan tagih dihitung dari
        // payer_flag (lihat JobCost::isBillable()).
        $job->load(['importir', 'shippingLine', 'costs' => fn ($q) => $q->where('payer_flag', '!=', JobCost::PAYER_IMPORTIR)]);

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

        // BUGFIX: Blok (b) sebelumnya hardcoded 0 sehingga biaya billable tidak
        // pernah masuk total invoice walau itemnya dibuat.
        $blokBTotal = (float) $billableCosts->sum('amount_idr');
        $blokBPPN = 0.0;

        // Blok (c) — Jasa PPJK + PPN 12% × DPP 11/12
        $serviceFee = (float) $data['blok_c_service_fee'];
        $dppLain = round($serviceFee * (11 / 12), 4);
        $ppnRate = (float) $data['blok_c_ppn_rate'];
        $ppnJasa = round($dppLain * $ppnRate, 4);

        $totalInvoice = $blokATotal + $blokBTotal + $serviceFee + $ppnJasa;

        // Generate nomor invoice berurutan (BUGFIX 2026-11-04: filter_var dengan
        // FILTER_SANITIZE_NUMBER_INT mempertahankan tanda '-' sehingga
        // 'INV-2026-000001' menjadi (int)'-2026-000001' = -2026 dan setiap
        // invoice setelah yang pertama bernomor 'INV-2026-0-2025' lalu
        // menabrak unique constraint pada invoice ke-3. Ambil urutan dari
        // digit terakhir nomor-nomor tahun berjalan, bukan dari string utuh.)
        $year = date('Y');
        $maxSequence = 0;
        foreach (Invoice::query()->where('invoice_number', 'like', "INV-{$year}-%")->pluck('invoice_number') as $existing) {
            if (preg_match('/(\d+)$/', $existing, $m)) {
                $maxSequence = max($maxSequence, (int) $m[1]);
            }
        }
        $nextNumber = $maxSequence + 1;
        $invoiceNumber = 'INV-' . $year . '-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

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
        $invoice->load([
            'job:id,job_number',
            'importir:id,name',
            'items',
            'payments.allocations',
            'creator:id,name',
            'approver:id,name',
        ]);

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

        // BUGFIX: hitung alokasi yang SUDAH tercatat sebelumnya untuk pembayaran ini
        // agar alokasi bertahap (batch terpisah) tidak bisa melampaui nominal payment.
        $previouslyAllocated = (float) $payment->allocations()->sum('amount');
        // BUGFIX 2026-11-04 (Q22): dana yang sudah dipindah ke Titipan Dana
        // dari pembayaran ini (kelebihan bayar) tidak boleh dialokasikan
        // dua kali — kurangi dari sisa yang bisa dialokasi.
        $existingTitipan = TitipanDana::query()
            ->where('reference', 'OVERPAY-PAY-'.$payment->id)
            ->get()
            ->sum(fn ($t) => $t->sisaSaldo());
        $sisaPayment = (float) $payment->amount - $previouslyAllocated - (float) $existingTitipan;

        DB::beginTransaction();
        try {
            $totalAllocatedThisRequest = 0;

            foreach ($data['allocations'] as $alloc) {
                // Batas: alokasi per baris tidak boleh melebihi sisa tagihan baris
                if (isset($alloc['invoice_item_id'])) {
                    $item = InvoiceItem::query()->findOrFail($alloc['invoice_item_id']);
                    // Pastikan item milik invoice yang dituju
                    if ((int) $item->invoice_id !== (int) $invoice->id) {
                        throw ValidationException::withMessages([
                            'allocations' => "Item invoice tidak cocok dengan invoice yang dipilih.",
                        ]);
                    }
                    $sisaBaris = (float) $item->total_line - (float) $item->allocations()->sum('amount');
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

                $totalAllocatedThisRequest += $alloc['amount'];
            }

            // Batas: total alokasi KUMULATIF tidak boleh melebihi jumlah pembayaran
            if ($totalAllocatedThisRequest > $sisaPayment) {
                throw ValidationException::withMessages([
                    'allocations' => "Total alokasi melebihi sisa dana pembayaran (tersisa Rp " . number_format($sisaPayment, 0, ',', '.') . ").",
                ]);
            }

            // Update invoice total_paid
            $invoice->total_paid = (float) $invoice->total_paid + $totalAllocatedThisRequest;
            $invoice->save();

            // Titipan Dana (Q22, T6 — BUGFIX 2026-11-04): Titipan Dana HANYA
            // terbentuk dari kelebihan bayar, yaitu saat invoice sudah lunas
            // penuh tetapi pembayaran masih bersisa. Sisa alokasi sebagian
            // (invoice belum lunas) TIDAK menjadi Titipan Dana — dana itu
            // masih bisa dialokasikan pada batch berikutnya. Idempotent per
            // payment lewat reference, supaya batch susulan tidak menduplikasi.
            $titipan = null;
            $sisaSetelahAlokasi = round((float) $payment->amount - ($previouslyAllocated + $totalAllocatedThisRequest), 4);
            if ($invoice->sisaTagihan() <= 0 && $sisaSetelahAlokasi > 0) {
                $titipan = TitipanDana::query()->firstOrCreate(
                    ['reference' => 'OVERPAY-PAY-'.$payment->id],
                    [
                        'company_id' => $request->user()->company_id,
                        'importir_id' => $invoice->importir_id,
                        'received_date' => $payment->payment_date,
                        'amount' => $sisaSetelahAlokasi,
                        'allocated_amount' => 0,
                        'method' => $payment->method,
                        'notes' => "Kelebihan bayar payment #{$payment->id} untuk invoice {$invoice->invoice_number} (Q22).",
                        'created_by_user_id' => $request->user()->id,
                    ]
                );

                Audit::record('titipan_dana.created', $titipan, [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $sisaSetelahAlokasi,
                ], 'Kelebihan bayar menjadi Titipan Dana Importir');
            }

            // Tandai payment sudah teralokasi penuh jika sisa habis
            $accounted = $previouslyAllocated + $totalAllocatedThisRequest + ($titipan ? (float) $titipan->amount : 0);
            if ($accounted >= (float) $payment->amount) {
                $payment->update(['is_unallocated' => false]);
            }

            // Cek lunas
            if ($invoice->sisaTagihan() <= 0) {
                $invoice->update(['status' => Invoice::STATUS_LOCKED]);
            }

            Audit::record('payment.allocated', $payment, [
                'invoice_id' => $invoice->id,
                'total_allocated' => $totalAllocatedThisRequest,
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

            // BUGFIX 2026-11-04: lihat ReportController::exposurePerImportir —
            // eksposur lama hanya menghitung JobCost (sumber Blok B) sehingga
            // importir yang sudah lunas penuh tampil NEGATIF. Tambahkan Blok
            // (a) pungutan negara dan Blok (c) jasa yang sudah ditagihkan;
            // batasi bawah 0. (Rumus yang sama dipakai di dua controller ini;
            // bila berubah, ubah keduanya.)
            $totalBilledBlocks = Invoice::query()
                ->where('importir_id', $imp->id)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->get()
                ->sum(fn ($inv) => (float) $inv->blok_a_total + (float) $inv->blok_c_service_fee + (float) $inv->blok_c_ppn);

            $titipanDana = TitipanDana::query()
                ->where('importir_id', $imp->id)
                ->get()
                ->sum(fn ($t) => $t->sisaSaldo());

            return [
                'importir_id' => $imp->id,
                'importir_name' => $imp->name,
                'exposure_bruto' => max(0, (float) $totalRealCost + $totalBilledBlocks - (float) $totalAllocated),
                'titipan_dana_saldo' => $titipanDana,
            ];
        });

        return Inertia::render('finance/exposure', [
            'exposures' => $exposures,
        ]);
    }
}