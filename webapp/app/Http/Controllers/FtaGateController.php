<?php

namespace App\Http\Controllers;

use App\Models\CustomsDeclaration;
use App\Models\CustomsItem;
use App\Models\FtaDecision;
use App\Models\FtaForm;
use App\Models\FtaGateOverride;
use App\Models\FtaTariff;
use App\Models\Job;
use App\Models\JobDocument;
use App\Services\FtaGate;
use App\Services\JobClose;
use App\Services\PibCalculator;
use App\Support\Audit;
use App\Support\FormatRules;
use App\Support\MakerChecker;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gerbang FTA (Tiket 10, Q19, §3.8): Form FTA sebagai Dokumen Asal,
 * override Customs Specialist atas blokir keras, dan decision log MFN.
 *
 * Integrasi dengan approve Snapshot PIB ada di PibCalculatorController::
 * approve() — gerbang ini tidak pernah di-bypass: approve memanggil
 * FtaGate::assertApprovable() setelah pemeriksaan ADR 0008.
 */
class FtaGateController extends Controller
{
    /**
     * Simpan Form FTA (metadata + lampiran opsional) untuk satu Job.
     * Bisa dicatat sebelum draf PIB ada — kecocokan ke baris HS dilakukan
     * lewat prefix 6 digit saat evaluasi gerbang; customs_item_id opsional
     * mengikat Form ke satu seri bila sudah diketahui.
     */
    public function storeForm(Request $request, Job $job): RedirectResponse
    {
        Gate::authorize('fta_form');
        // AUDIT P3-5: konsistensi defense-in-depth dengan endpoint Job-scoped lain.
        Gate::authorize('view', $job);
        // AUDIT P2-3: Job Closed adalah kunci tulis — storeForm menimpa
        // fta_forms.file_path dan me-reset status dokumen form_fta pada
        // checklist, jadi harus ditolak seperti upload dokumen biasa.
        JobClose::assertNotClosed($job);

        $data = $request->validate([
            'scheme_code' => ['required', 'string', 'in:'.implode(',', array_keys(FtaTariff::SCHEMES))],
            'form_number' => ['required', 'string', 'max:100'],
            'hs_code' => ['required', 'string', 'max:14', function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = FormatRules::normalizeHsCode((string) $value);
                if (strlen($digits) < 6 || strlen($digits) > 10) {
                    $fail('Kode HS pada Form harus 6–10 digit (Form memakai 6 digit, PIB 8/10 digit).');
                }
            }],
            'origin_country' => ['required', 'string', 'size:2', 'alpha'],
            'port_of_loading' => ['required', 'string', 'max:100'],
            'issued_at' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issued_at'],
            'customs_item_id' => ['nullable', 'integer', 'exists:customs_items,id'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        $companyId = $request->user()->company_id;
        $hsDigits = FormatRules::normalizeHsCode($data['hs_code']);

        // customs_item_id — bila diisi — harus milik Job ini.
        $item = null;
        if (! empty($data['customs_item_id'])) {
            $item = CustomsItem::query()->findOrFail($data['customs_item_id']);
            if ((int) $item->declaration->job_id !== (int) $job->id) {
                abort(404, 'Seri PIB tidak ditemukan pada Job ini.');
            }
        }

        $filePath = null;
        $fileName = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store("fta-forms/{$companyId}/{$job->id}", 'local');
            $fileName = $request->file('file')->getClientOriginalName();
        } else {
            // Tanpa lampiran baru, pertahankan lampiran Form yang sudah ada.
            $filePath = FtaForm::query()
                ->where('job_id', $job->id)
                ->where('form_number', $data['form_number'])
                ->value('file_path');
            $fileName = FtaForm::query()
                ->where('job_id', $job->id)
                ->where('form_number', $data['form_number'])
                ->value('file_name');
        }

        $form = FtaForm::query()->updateOrCreate(
            ['job_id' => $job->id, 'form_number' => $data['form_number']],
            [
                'company_id' => $companyId,
                'customs_item_id' => $item?->id,
                'scheme_code' => $data['scheme_code'],
                'hs_code' => $hsDigits,
                'origin_country' => strtoupper($data['origin_country']),
                'port_of_loading' => $data['port_of_loading'],
                'issued_at' => $data['issued_at'],
                'valid_until' => $data['valid_until'],
                'file_path' => $filePath,
                'file_name' => $fileName,
                'created_by_user_id' => $request->user()->id,
            ]
        );

        // Cerminkan ke baris checklist Form FTA Job (Q21): bila ada
        // lampiran, dokumen checklist ikut berstatus diupload agar progres
        // "Dokumen Lengkap" konsisten dengan Form yang sudah dicatat.
        if ($filePath) {
            JobDocument::query()->updateOrCreate(
                ['job_id' => $job->id, 'document_type' => JobDocument::TYPE_FORM_FTA],
                [
                    'company_id' => $companyId,
                    'phase' => JobDocument::PHASE_PRA_FILING,
                    'status' => JobDocument::STATUS_DIUPLOAD,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'uploaded_by_user_id' => $request->user()->id,
                    'uploaded_at' => now(),
                ]
            );
        }

        Audit::record('fta_form.stored', $form, [
            'scheme' => $form->scheme_code,
            'form_number' => $form->form_number,
            'hs_code' => $form->hs_code,
        ], "Form FTA {$form->scheme_code} {$form->form_number} dicatat untuk Job {$job->job_number}");

        return redirect()->back();
    }

    /** Unduh lampiran Form FTA lewat endpoint ber-Gate (disk privat). */
    public function downloadForm(Request $request, Job $job, FtaForm $form): StreamedResponse
    {
        Gate::authorize('view', $job);

        if ((int) $form->job_id !== (int) $job->id) {
            abort(404, 'Form FTA tidak ditemukan pada Job ini.');
        }

        return PrivateFile::download($form->file_path, $form->file_name);
    }

    /**
     * Override atas blokir keras gerbang FTA — hanya Customs Specialist,
     * wajib alasan + lampiran, dan overrider harus berbeda dari pembuat
     * draf PIB (maker-checker, ADR 0006). Satu override per (declaration,
     * item); override tidak menghapus blokirnya — ia mencatat pengecualian
     * yang sah sehingga approve dapat lanjut.
     */
    public function storeOverride(Request $request, Job $job, CustomsDeclaration $declaration): RedirectResponse
    {
        Gate::authorize('override_fta');

        if ((int) $declaration->job_id !== (int) $job->id) {
            abort(404, 'Deklarasi tidak ditemukan pada Job ini.');
        }

        // Override hanya bermakna untuk draf — snapshot yang sudah
        // disetujui tidak boleh diubah (ADR 0004, immutability).
        if ($declaration->status !== CustomsDeclaration::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'override' => 'Deklarasi ini sudah bukan draf — override gerbang FTA tidak dapat dicatat.',
            ]);
        }

        $data = $request->validate([
            'customs_item_id' => ['required', 'integer', 'exists:customs_items,id'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['required', 'file', 'max:20480'],
        ]);

        $item = CustomsItem::query()->findOrFail($data['customs_item_id']);
        if ((int) $item->customs_declaration_id !== (int) $declaration->id) {
            abort(404, 'Seri PIB tidak ditemukan pada deklarasi ini.');
        }

        // Maker-checker: pembuat draf tidak boleh meng-override blokirnya
        // sendiri — overrider harus user lain (ADR 0006).
        MakerChecker::assertApproverDiffers($request->user(), $declaration);

        // Override hanya bermakna bila seri itu memang diblokir gerbang.
        $evaluation = app(FtaGate::class)->evaluate($job, $declaration);
        $itemResult = collect($evaluation['items'])->firstWhere('customs_item_id', $item->id);
        if (! $itemResult || $itemResult['status'] !== FtaGate::STATUS_DIBLOKIR) {
            throw ValidationException::withMessages([
                'override' => 'Seri ini tidak sedang diblokir gerbang FTA — override tidak diperlukan.',
            ]);
        }

        $companyId = $request->user()->company_id;
        $attachmentPath = $request->file('attachment')->store("fta-overrides/{$companyId}/{$job->id}", 'local');

        $form = $itemResult['form_id'] ? FtaForm::query()->find($itemResult['form_id']) : null;

        $override = FtaGateOverride::query()->updateOrCreate(
            [
                'customs_declaration_id' => $declaration->id,
                'customs_item_id' => $item->id,
            ],
            [
                'company_id' => $companyId,
                'fta_form_id' => $form?->id,
                'reason' => $data['reason'],
                'attachment_path' => $attachmentPath,
                'attachment_name' => $request->file('attachment')->getClientOriginalName(),
                'created_by_user_id' => $request->user()->id,
            ]
        );

        Audit::record('fta_gate.overridden', $override, [
            'declaration_id' => $declaration->id,
            'item_number' => $item->item_number,
            'hs_code' => $item->hs_code,
            'reason' => $data['reason'],
        ], "Override gerbang FTA seri {$item->item_number} (HS {$item->hs_code}) oleh Customs Specialist");

        return redirect()->back();
    }

    /**
     * Keputusan filing bila Form belum ada (Q19): "file MFN now" atau
     * "delay". Keputusan dicatat append-only di decision log (siapa, kapan,
     * alasan, risiko). Pilihan mfn_now mengunci Job ke MFN: klaim FTA Job
     * dimatikan secara eksplisit dan tercatat (bukan diam-diam), sehingga
     * draf berikutnya dihitung MFN dan versi PIB yang sama tidak bisa
     * diam-diam diubah kembali ke FTA.
     */
    public function storeDecision(Request $request, Job $job, CustomsDeclaration $declaration): RedirectResponse
    {
        Gate::authorize('calculator_pib');

        if ((int) $declaration->job_id !== (int) $job->id) {
            abort(404, 'Deklarasi tidak ditemukan pada Job ini.');
        }

        // Keputusan hanya untuk draf — snapshot yang sudah disetujui
        // tidak boleh diubah (ADR 0004, immutability).
        if ($declaration->status !== CustomsDeclaration::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'decision_type' => 'Deklarasi ini sudah bukan draf — keputusan filing tidak dapat dicatat.',
            ]);
        }

        $data = $request->validate([
            'decision_type' => ['required', 'string', 'in:'.FtaDecision::TYPE_MFN_NOW.','.FtaDecision::TYPE_DELAY],
            'reason' => ['required', 'string', 'max:1000'],
            'risk_note' => ['nullable', 'string', 'max:1000'],
            'refund_reference' => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['decision_type'] === FtaDecision::TYPE_MFN_NOW
            && FtaDecision::query()
                ->where('customs_declaration_id', $declaration->id)
                ->where('decision_type', FtaDecision::TYPE_MFN_NOW)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'decision_type' => 'Deklarasi ini sudah diputuskan file MFN — keputusan MFN tidak dapat dicatat dua kali untuk versi PIB yang sama.',
            ]);
        }

        $decision = DB::transaction(function () use ($request, $job, $declaration, $data) {
            $decision = FtaDecision::query()->create([
                'company_id' => $request->user()->company_id,
                'job_id' => $job->id,
                'customs_declaration_id' => $declaration->id,
                'decision_type' => $data['decision_type'],
                'reason' => $data['reason'],
                'risk_note' => $data['risk_note'] ?? null,
                'refund_reference' => $data['refund_reference'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);

            if ($data['decision_type'] === FtaDecision::TYPE_MFN_NOW) {
                if ($job->claim_fta) {
                    $job->update(['claim_fta' => false]);
                }

                // "File MFN sekarang" harus filing dengan angka MFN — draf
                // yang tadinya dihitung dengan tarif FTA dihitung ulang
                // tanpa skema FTA, supaya Snapshot PIB yang disetujui
                // memang berisi pungutan MFN (bukan angka FTA yang tertinggal).
                $this->recomputeDeclarationAtMfn($declaration, $job);
            }

            return $decision;
        });

        Audit::record('fta_decision.recorded', $decision, [
            'decision_type' => $data['decision_type'],
            'declaration_id' => $declaration->id,
            'reason' => $data['reason'],
        ], $data['decision_type'] === FtaDecision::TYPE_MFN_NOW
            ? "Keputusan: file MFN sekarang (tanpa klaim FTA) — Job {$job->job_number}"
            : "Keputusan: tunda filing menunggu Form FTA — Job {$job->job_number}");

        return redirect()->back();
    }

    /**
     * Catat nomor referensi refund eksternal MFN→FTA (proses manual di luar
     * sistem v1, Q19): ditempelkan pada keputusan mfn_now yang sudah ada.
     */
    public function updateRefundReference(Request $request, Job $job, FtaDecision $decision): RedirectResponse
    {
        Gate::authorize('calculator_pib');

        if ((int) $decision->job_id !== (int) $job->id || $decision->decision_type !== FtaDecision::TYPE_MFN_NOW) {
            abort(404, 'Keputusan MFN tidak ditemukan pada Job ini.');
        }

        $data = $request->validate([
            'refund_reference' => ['required', 'string', 'max:100'],
        ]);

        $decision->update(['refund_reference' => $data['refund_reference']]);

        Audit::record('fta_decision.refund_reference', $decision, [
            'refund_reference' => $data['refund_reference'],
        ], "Referensi refund MFN→FTA dicatat untuk Job {$job->job_number}");

        return redirect()->back();
    }

    /**
     * Hitung ulang satu draf PIB tanpa skema FTA (tarif MFN) dari input
     * seri yang sudah tersimpan — dipakai saat keputusan "file MFN now".
     * Semua seri + total deklarasi diperbarui supaya Snapshot PIB yang
     * nantinya disetujui memang berisi angka MFN.
     */
    private function recomputeDeclarationAtMfn(CustomsDeclaration $declaration, Job $job): void
    {
        $calculator = app(PibCalculator::class);
        $statusApi = $job->importir->status_api ?? 'API-U';

        $inputs = $declaration->items()->orderBy('item_number')->get()->map(fn (CustomsItem $item) => [
            'hs_code' => $item->hs_code,
            'description' => $item->description,
            'currency_code' => $item->currency_code,
            'fob_valas' => $item->fob_valas,
            'freight_valas' => $item->freight_valas,
            'insurance_valas' => $item->insurance_valas,
            'insurance_basis' => $item->insurance_basis,
            'kurs_pajak_rate' => $item->kurs_pajak_rate,
            'is_ppn_luxury' => $item->is_ppn_luxury,
            'is_pph22_exempt' => $item->is_pph22_exempt,
            'exemption_reason' => $item->exemption_reason,
            'pungutan_lain_amount' => $item->pungutan_lain_amount,
        ])->all();

        $calc = $calculator->calculateDeclaration($inputs, $statusApi, null, now());

        foreach ($calc['items'] as $calculated) {
            $declaration->items()
                ->where('item_number', $calculated['item_number'])
                ->update(collect($calculated)->except('item_number')->all());
        }

        $declaration->update([
            'total_cif_valas' => $calc['total_cif_valas'],
            'total_cif_idr' => $calc['total_cif_idr'],
            'total_bm' => $calc['total_bm'],
            'total_ppn' => $calc['total_ppn'],
            'total_pph22' => $calc['total_pph22'],
            'total_ppnbm' => $calc['total_ppnbm'],
            'total_pungutan_lain' => $calc['total_pungutan_lain'],
            'total_pungutan' => $calc['total_pungutan'],
        ]);
    }
}
