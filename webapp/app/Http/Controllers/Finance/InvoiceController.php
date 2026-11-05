<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CustomsDeclaration;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceNumberCounter;
use App\Models\Job;
use App\Models\JobCost;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\TitipanDana;
use App\Services\JobClose;
use App\Support\Audit;
use Illuminate\Database\QueryException;
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
        // HARDENING 2026-11-04 (temuan #1): pembuatan invoice juga wajib
        // lolos akses Job — sebelumnya hanya ability global, sehingga Job
        // perusahaan lain bisa dibuka lewat route binding.
        Gate::authorize('view', $job);

        // HARDENING 2026-11-04: preview memakai SATU definisi billable yang
        // sama dengan store() (Job::billableCosts) — sebelumnya filter preview
        // dan submit berbeda, sehingga angka yang dilihat Finance belum tentu
        // angka yang akhirnya ditagihkan.
        $job->load(['importir', 'shippingLine']);

        // TEMUAN LANJUTAN (2026-11-05): beri tahu Finance bila Blok A / jasa
        // sudah ditagih — invoice susulan hanya menagih yang belum ditagih.
        $alreadyBilledA = Invoice::query()
            ->where('job_id', $job->id)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('blok_a_total', '>', 0)
            ->orderByDesc('id')
            ->first(['invoice_number']);
        $alreadyBilledC = Invoice::query()
            ->where('job_id', $job->id)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('blok_c_service_fee', '>', 0)
            ->orderByDesc('id')
            ->first(['invoice_number']);

        return Inertia::render('finance/invoice-create', [
            'job' => $job,
            'billableCosts' => $job->billableCosts()->get(),
            'alreadyBilledA' => $alreadyBilledA ? ['invoice_number' => $alreadyBilledA->invoice_number] : null,
            'alreadyBilledC' => $alreadyBilledC ? ['invoice_number' => $alreadyBilledC->invoice_number] : null,
        ]);
    }

    public function store(Request $request, Job $job): RedirectResponse
    {
        Gate::authorize('invoice');
        // HARDENING 2026-11-04 (temuan #1): sama seperti create() — store
        // invoice wajib lolos akses Job.
        Gate::authorize('view', $job);

        // KUNCI TUTUP (§2.3, Q11): Job tertutup tidak menerima invoice baru.
        JobClose::assertNotClosed($job);

        $data = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'blok_c_service_fee' => ['required', 'numeric', 'min:0'],
            'blok_c_ppn_rate' => ['required', 'numeric', 'min:0', 'max:0.13'],
            'faktur_pajak_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $importir = $job->importir;
        $snapshot = CustomsDeclaration::query()
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

        // Blok (c) — Jasa PPJK + PPN 12% × DPP 11/12 (murni fungsi input)
        $serviceFee = (float) $data['blok_c_service_fee'];
        $dppLain = round($serviceFee * (11 / 12), 4);
        $ppnRate = (float) $data['blok_c_ppn_rate'];
        $ppnJasa = round($dppLain * $ppnRate, 4);

        // SATU transaksi untuk: baca biaya billable (terkunci) -> buat invoice
        // + items -> tandai biaya sudah ditagih. Bila ada langkah gagal
        // (termasuk guard double-billing di bawah), semuanya batal.
        // (HARDENING 2026-11-04.)
        $invoice = DB::transaction(function () use ($request, $job, $data, $importir, $snapshot, $blokA, $blokATotal, $serviceFee, $dppLain, $ppnRate, $ppnJasa) {

            // TEMUAN AUDIT 8 ITEM (2026-11-05, item 4): serialisasi per Job.
            // Kunci baris Job di awal transaksi — invoice kedua untuk Job
            // yang sama menunggu sampai transaksi pertama selesai, sehingga
            // guard Blok A/C di bawah selalu membaca state terbaru dan
            // tidak ada dua invoice pertama yang sama-sama lolos guard.
            // Tanpa kunci ini, dua request bersamaan sama-sama melihat
            // "belum ada invoice" dan yang kedua baru gagal di unique
            // constraint nomor (500), bukan error yang bersih.
            Job::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            // TEMUAN LANJUTAN (2026-11-05): Blok A dan Blok C tidak boleh
            // ditagih dua kali untuk Job yang sama. Guard sebelumnya hanya
            // mencakup Blok B (job_costs.invoice_id). Invoice susulan tetap
            // bisa dibuat — hanya untuk biaya Blok B yang belum ditagih
            // (spesifikasi §6.4: koreksi invoice terkunci lewat nota
            // kredit/debit, bukan invoice ulang).
            //  - Blok C adalah input user: tolak bila jasa sudah ditagih.
            //  - Blok A bukan input user (otomatis dari Snapshot): nol-kan
            //    di server dengan jejak audit, supaya invoice susulan Blok B
            //    tetap bisa dibuat.
            $previousBilled = Invoice::query()
                ->where('job_id', $job->id)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->lockForUpdate()
                ->get();
            $previousInvoiceA = $previousBilled->first(fn ($inv) => (float) $inv->blok_a_total > 0);
            $previousInvoiceC = $previousBilled->first(fn ($inv) => (float) $inv->blok_c_service_fee > 0);

            if ($previousInvoiceC && $serviceFee > 0) {
                throw ValidationException::withMessages([
                    'blok_c_service_fee' => "Jasa PPJK sudah ditagih pada invoice {$previousInvoiceC->invoice_number} — isi Service Fee 0 untuk invoice susulan (hanya biaya Blok B baru yang bisa ditagih).",
                ]);
            }

            $blokAExcludedNote = null;
            if ($previousInvoiceA && $blokATotal > 0) {
                $blokAExcludedNote = "Blok (a) sudah ditagih pada invoice {$previousInvoiceA->invoice_number} — tidak ditagih ulang pada invoice ini.";
                $blokA = ['bm' => 0, 'ppn_impor' => 0, 'pph22' => 0, 'ppnbm' => 0, 'pungutan_lain' => 0];
                $blokATotal = 0;
            }

            // Blok (b) — biaya pihak ketiga (pass-through tanpa markup).
            // HARDENING 2026-11-04: daftar biaya dibaca ulang DI DALAM transaksi
            // dengan row lock (Job::billableCosts — definisi yang SAMA dengan
            // preview di create()). Biaya yang sudah ditagih invoice lain tidak
            // bisa masuk dua kali: lock + guard whereNull('invoice_id') pada
            // penandaan di bawah, plus hitung jumlah yang berhasil ditandai.
            $billableCosts = $job->billableCosts()->lockForUpdate()->get();

            // BUGFIX: Blok (b) sebelumnya hardcoded 0 sehingga biaya billable tidak
            // pernah masuk total invoice walau itemnya dibuat.
            $blokBTotal = (float) $billableCosts->sum('amount_idr');
            $blokBPPN = 0.0;

            $totalInvoice = $blokATotal + $blokBTotal + $serviceFee + $ppnJasa;

            // Nomor invoice dari counter per tahun (temuan audit 8 item,
            // item 4) — baris counter dikunci di dalam transaksi yang sama,
            // jadi dua invoice bersamaan mendapat urutan berbeda. Pengganti
            // `max + 1` yang menghitung urutan dari daftar nomor: dua
            // request bersamaan menghitung angka yang sama dan yang kedua
            // menabrak unique constraint (500).
            $year = (int) date('Y');
            $nextNumber = self::nextInvoiceSequence($year);
            $invoiceNumber = 'INV-'.$year.'-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

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
                // Snapshot hanya di-link bila pungutannya benar-benar
                // ditagih invoice ini (bila Blok A dinol-kan karena sudah
                // ditagih sebelumnya, link dikosongkan).
                'snapshot_pib_id' => $blokATotal > 0 ? $snapshot?->id : null,
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

            // Tandai biaya sudah ditagih (HARDENING 2026-11-04): tanpa ini satu
            // JobCost bisa masuk invoice kedua. Guard whereNull('invoice_id')
            // + hitung baris yang berhasil ditandai — bila ada biaya yang sempat
            // ditagih proses lain di antara baca dan tandai, transaksi dibatalkan.
            $marked = $job->costs()
                ->whereIn('id', $billableCosts->pluck('id'))
                ->whereNull('invoice_id')
                ->update(['invoice_id' => $invoice->id, 'invoiced_at' => now()]);

            if ($marked !== $billableCosts->count()) {
                throw ValidationException::withMessages([
                    'job' => 'Sebagian biaya sudah ditagih pada invoice lain — muat ulang halaman dan periksa kembali sebelum membuat invoice.',
                ]);
            }

            Audit::record('invoice.created', $invoice, [
                'invoice_number' => $invoiceNumber,
                'blok_a_excluded' => $blokAExcludedNote,
            ], 'Pembuatan draf invoice');

            return $invoice;
        });

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

        // KUNCI TUTUP (§2.3, Q11): persetujuan invoice adalah tulis.
        JobClose::assertNotClosed($invoice->job);

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

        // KUNCI TUTUP (§2.3, Q11): pembayaran ke invoice Job tertutup ditolak
        // — tagihan Job tertutup sudah lunas; pembayaran baru hanya akan
        // menjadi kelebihan bayar.
        JobClose::assertNotClosed($invoice->job);

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

        // HARDENING 2026-11-04 (race alokasi): seluruh baca saldo + tulis
        // alokasi berjalan dalam SATU transaksi dengan row lock pada payment,
        // invoice, dan item yang dialokasi. Sebelumnya saldo dibaca DI LUAR
        // transaksi tanpa lock — dua request bersamaan bisa sama-sama melihat
        // sisa penuh dan sama-sama mengalokasi, sehingga total alokasi
        // melampaui nominal pembayaran. DB::transaction() juga memastikan
        // rollback untuk SEMUA exception, bukan hanya ValidationException
        // (kode lama membiarkan transaksi menggantung bila exception lain
        // terjadi di tengah jalan).
        return DB::transaction(function () use ($request, $payment, $data) {
            // Kunci baris dulu — request lain yang menyentuh payment/invoice
            // yang sama menunggu di sini sampai transaksi ini selesai.
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);

            // KUNCI TUTUP (§2.3, Q11): alokasi ke invoice Job tertutup ditolak.
            JobClose::assertNotClosed($invoice->job);

            // TEMUAN #3 (2026-11-04): pembayaran hanya boleh dialokasikan ke
            // invoice milik importir yang SAMA. Sebelumnya endpoint hanya
            // memeriksa item milik invoice — payment importir A bisa
            // dialokasikan ke invoice importir B tanpa jejak. Cek dilakukan
            // di dalam transaksi setelah lock, sebelum baris apa pun dibuat,
            // sehingga penolakan tidak menyisakan alokasi setengah jalan.
            if ((int) $payment->importir_id !== (int) $invoice->importir_id) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Pembayaran ini milik importir lain dan tidak dapat dialokasikan ke invoice ini.',
                ]);
            }

            // Hitung alokasi yang SUDAH tercatat sebelumnya untuk pembayaran
            // ini agar alokasi bertahap (batch terpisah) tidak bisa melampaui
            // nominal payment. Dana yang sudah dipindah ke Titipan Dana dari
            // pembayaran ini (kelebihan bayar, Q22) tidak boleh dialokasikan
            // dua kali — kurangi dari sisa yang bisa dialokasi.
            $previouslyAllocated = (float) $payment->allocations()->sum('amount');
            $existingTitipan = TitipanDana::query()
                ->where('reference', 'OVERPAY-PAY-'.$payment->id)
                ->get()
                ->sum(fn ($t) => $t->sisaSaldo());
            $sisaPayment = (float) $payment->amount - $previouslyAllocated - (float) $existingTitipan;

            $totalAllocatedThisRequest = 0;

            foreach ($data['allocations'] as $alloc) {
                // Batas: alokasi per baris tidak boleh melebihi sisa tagihan baris
                if (isset($alloc['invoice_item_id'])) {
                    $item = InvoiceItem::query()->lockForUpdate()->findOrFail($alloc['invoice_item_id']);
                    // Pastikan item milik invoice yang dituju
                    if ((int) $item->invoice_id !== (int) $invoice->id) {
                        throw ValidationException::withMessages([
                            'allocations' => 'Item invoice tidak cocok dengan invoice yang dipilih.',
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

            // TEMUAN AUDIT 8 ITEM (2026-11-05, item 5): total alokasi juga
            // dibatasi SISA TAGIHAN invoice — bukan hanya sisa pembayaran.
            // Tanpa invoice_item_id, cek per-baris di atas dilewati sehingga
            // alokasi bisa melampaui total invoice selama masih dalam
            // nominal payment (total_paid > total_invoice). Baris invoice
            // sudah terkunci, jadi sisaTagihan() di sini adalah saldo
            // sebelum request ini — batas yang benar untuk batch ini.
            if ($totalAllocatedThisRequest > (float) $invoice->sisaTagihan()) {
                throw ValidationException::withMessages([
                    'allocations' => 'Total alokasi melebihi sisa tagihan invoice (tersisa Rp '.number_format($invoice->sisaTagihan(), 0, ',', '.').').',
                ]);
            }

            // Batas: total alokasi KUMULATIF tidak boleh melebihi jumlah pembayaran
            if ($totalAllocatedThisRequest > $sisaPayment) {
                throw ValidationException::withMessages([
                    'allocations' => 'Total alokasi melebihi sisa dana pembayaran (tersisa Rp '.number_format($sisaPayment, 0, ',', '.').').',
                ]);
            }

            // Update invoice total_paid (baris invoice sudah terkunci).
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

            return redirect()->back();
        });
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

    /**
     * Ambil urutan nomor invoice berikutnya untuk satu tahun dari counter
     * (temuan audit 8 item 2026-11-05, item 4). WAJIB dipanggil di dalam
     * transaksi pembuatan invoice: baris counter dikunci (`lockForUpdate`)
     * sehingga dua invoice bersamaan mendapat urutan berbeda. Bila baris
     * tahun berjalan belum ada (invoice pertama tahun itu), dibuat di sini
     * — balapan pembuatan baris ditangani dengan percobaan ulang singkat
     * (unique constraint pada `year` membuat satu pihak menang, pihak lain
     * membaca baris yang sudah ada).
     */
    private static function nextInvoiceSequence(int $year): int
    {
        $lastError = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $counter = InvoiceNumberCounter::query()->lockForUpdate()->where('year', $year)->first();

            if (! $counter) {
                try {
                    $counter = InvoiceNumberCounter::query()->create([
                        'year' => $year,
                        'next_sequence' => 1,
                    ]);
                } catch (QueryException $e) {
                    // Baris dibuat transaksi lain di saat yang sama — coba
                    // lagi membaca (dengan kunci) pada percobaan berikutnya.
                    $lastError = $e;

                    continue;
                }
            }

            $sequence = (int) $counter->next_sequence;
            $counter->increment('next_sequence');

            return $sequence;
        }

        throw $lastError ?? new \RuntimeException('Gagal mengambil nomor invoice berikutnya.');
    }
}
