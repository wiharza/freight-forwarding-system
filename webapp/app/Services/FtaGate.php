<?php

namespace App\Services;

use App\Models\CustomsDeclaration;
use App\Models\CustomsItem;
use App\Models\FtaDecision;
use App\Models\FtaForm;
use App\Models\FtaGateOverride;
use App\Models\Job;
use App\Support\FormatRules;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Gerbang FTA (Tiket 10, Q19, §3.8).
 *
 * Saat filing (approve Snapshot PIB), sistem memeriksa Form FTA yang
 * metadata-nya terikat ke baris HS PIB:
 *  - HS: prefix 6 digit Form ↔ PIB (pencocokan string penuh akan menolak
 *    klaim yang benar — spec §3.8).
 *  - Negara asal: kode negara ISO 2 huruf Form ↔ 2 huruf pertama kode
 *    UN/LOCODE pelabuhan muat (POL) Job.
 *  - Pelabuhan muat: Form ↔ POL Job (kode atau nama).
 *  - Masa berlaku: tanggal filing tidak boleh lewat valid_until Form.
 *
 * Mismatch mana pun = peringatan keras: filing diblokir kecuali Customs
 * Specialist melakukan override (alasan + lampiran, maker-checker, audit).
 * Pengecualian T5 (diputuskan 2026-11-04, dicatat di laporan): 6 digit
 * cocok tetapi digit 7–8 berbeda = peringatan NON-BLOKIR yang dicatat di
 * decision log, karena aturan terkunci Q19 memakai prefix 6 digit.
 *
 * Evaluasi ini murni (tanpa tulis) kecuali recordProceedingWarnings(),
 * yang dipanggil approve() sesaat sebelum menyetujui.
 */
class FtaGate
{
    public const STATUS_LOLOS = 'lolos';

    public const STATUS_PERINGATAN = 'peringatan';

    public const STATUS_DIBLOKIR = 'diblokir';

    public const STATUS_MFN = 'mfn';

    public const STATUS_OVERRIDE = 'override';

    public static function normalizeHs(string $hsCode): string
    {
        return FormatRules::normalizeHsCode($hsCode);
    }

    public static function prefix6(string $hsCode): string
    {
        return substr(self::normalizeHs($hsCode), 0, 6);
    }

    /**
     * Cari Form FTA untuk satu baris HS PIB: utamakan Form yang terikat
     * langsung ke item itu, lalu Form terbaru dengan prefix 6 digit sama.
     */
    public static function matchingForm(Job $job, CustomsItem $item): ?FtaForm
    {
        $bound = FtaForm::query()
            ->where('job_id', $job->id)
            ->where('customs_item_id', $item->id)
            ->latest()
            ->first();

        if ($bound) {
            return $bound;
        }

        $prefix = self::prefix6($item->hs_code);

        return FtaForm::query()
            ->where('job_id', $job->id)
            ->whereRaw("substr(REPLACE(hs_code, '.', ''), 1, 6) = ?", [$prefix])
            ->latest()
            ->first();
    }

    /** Apakah Job ini sudah diputuskan "file MFN now" (keputusan terakhir yang berlaku). */
    public static function hasMfnDecision(Job $job): bool
    {
        return FtaDecision::query()
            ->where('job_id', $job->id)
            ->where('decision_type', FtaDecision::TYPE_MFN_NOW)
            ->exists();
    }

    public static function declarationHasMfnDecision(CustomsDeclaration $declaration): bool
    {
        return FtaDecision::query()
            ->where('customs_declaration_id', $declaration->id)
            ->where('decision_type', FtaDecision::TYPE_MFN_NOW)
            ->exists();
    }

    /**
     * Evaluasi gerbang untuk satu deklarasi. Murni — tidak menulis apa pun.
     *
     * @return array{claim_fta: bool, mfn_decided: bool, overall: string, items: array<int, array{customs_item_id: int, item_number: int, hs_code: string, bm_type: string, status: string, form_id: int|null, checks: array<int, string>, reasons: array<int, string>}>}
     */
    public function evaluate(Job $job, CustomsDeclaration $declaration, ?CarbonInterface $filingDate = null): array
    {
        $filingDate = $filingDate ? Carbon::parse($filingDate) : now();
        $job->loadMissing(['pol']);

        $mfnDecided = self::hasMfnDecision($job) || self::declarationHasMfnDecision($declaration);

        if (! $job->claim_fta || $mfnDecided) {
            return [
                'claim_fta' => (bool) $job->claim_fta,
                'mfn_decided' => $mfnDecided,
                'overall' => self::STATUS_MFN,
                'items' => $declaration->items->map(fn (CustomsItem $item) => [
                    'customs_item_id' => $item->id,
                    'item_number' => $item->item_number,
                    'hs_code' => $item->hs_code,
                    'bm_type' => $item->bm_type,
                    'status' => self::STATUS_MFN,
                    'form_id' => null,
                    'checks' => [$mfnDecided ? 'mfn_decided' : 'tidak_klaim_fta'],
                    'reasons' => [$mfnDecided
                        ? 'Job ini sudah diputuskan file MFN (keputusan tercatat) — filing memakai tarif MFN.'
                        : 'Job ini tidak klaim FTA — filing memakai tarif MFN.'],
                ])->all(),
            ];
        }

        $items = [];
        $hasBlock = false;
        $hasWarning = false;

        foreach ($declaration->items as $item) {
            $result = $this->evaluateItem($job, $declaration, $item, $filingDate);
            $items[] = $result;

            if ($result['status'] === self::STATUS_DIBLOKIR) {
                $hasBlock = true;
            } elseif ($result['status'] === self::STATUS_PERINGATAN) {
                $hasWarning = true;
            }
        }

        return [
            'claim_fta' => true,
            'mfn_decided' => false,
            'overall' => $hasBlock ? self::STATUS_DIBLOKIR : ($hasWarning ? self::STATUS_PERINGATAN : self::STATUS_LOLOS),
            'items' => $items,
        ];
    }

    /** @return array{customs_item_id: int, item_number: int, hs_code: string, bm_type: string, status: string, form_id: int|null, checks: array<int, string>, reasons: array<int, string>} */
    private function evaluateItem(Job $job, CustomsDeclaration $declaration, CustomsItem $item, CarbonInterface $filingDate): array
    {
        $base = [
            'customs_item_id' => $item->id,
            'item_number' => $item->item_number,
            'hs_code' => $item->hs_code,
            'bm_type' => $item->bm_type,
            'form_id' => null,
            'checks' => [],
            'reasons' => [],
        ];

        // Seri yang dihitung MFN (tidak ada tarif FTA untuk HS ini) tidak
        // butuh Form — tidak ada preferensi yang diklaim atas seri itu.
        if ($item->bm_type !== 'fta') {
            $base['status'] = self::STATUS_MFN;
            $base['checks'][] = 'mfn_rate';
            $base['reasons'][] = 'Tarif FTA tidak berlaku untuk seri ini — dihitung dengan tarif MFN.';

            return $base;
        }

        $form = self::matchingForm($job, $item);

        if (! $form) {
            $base['status'] = self::STATUS_DIBLOKIR;
            $base['checks'][] = 'form_missing';
            $base['reasons'][] = 'Form FTA belum ada untuk HS '.self::prefix6($item->hs_code).'xxxx (prefix 6 digit) — klaim FTA tidak dapat difiling tanpa Form.';

            return $this->applyOverride($declaration, $base);
        }

        $base['form_id'] = $form->id;
        $base['checks'][] = 'hs_cocok';

        $blocked = false;
        $warned = false;

        // T5: 6 digit cocok, digit 7–8 berbeda = peringatan NON-BLOKIR.
        $formHs = self::normalizeHs($form->hs_code);
        $itemHs = self::normalizeHs($item->hs_code);
        if (strlen($formHs) >= 8 && strlen($itemHs) >= 8 && substr($formHs, 6, 2) !== substr($itemHs, 6, 2)) {
            $warned = true;
            $base['checks'][] = 'digits8_warning';
            $base['reasons'][] = 'Perhatian: 6 digit pertama HS cocok, tetapi digit 7–8 berbeda (Form '.$formHs.' vs PIB '.$itemHs.') — peringatan, filing tetap lanjut dan dicatat di decision log (T5).';
        }

        // Negara asal: 2 huruf pertama kode UN/LOCODE POL.
        $polCode = strtoupper(trim((string) ($job->pol?->code ?? '')));
        $polCountry = strlen($polCode) >= 2 && ctype_alpha(substr($polCode, 0, 2)) ? substr($polCode, 0, 2) : null;
        if ($polCountry === null) {
            $base['checks'][] = 'origin_unchecked';
            $base['reasons'][] = 'Negara asal tidak dapat diperiksa — kode POL Job tidak memuat prefix negara (bukan UN/LOCODE).';
        } elseif ($polCountry !== strtoupper($form->origin_country)) {
            $blocked = true;
            $base['checks'][] = 'origin_mismatch';
            $base['reasons'][] = 'Negara asal Form ('.strtoupper($form->origin_country).') tidak cocok dengan negara POL ('.$polCountry.' dari '.$polCode.').';
        } else {
            $base['checks'][] = 'origin_cocok';
        }

        // Pelabuhan muat: kode atau nama POL.
        if ($this->portMatches($form->port_of_loading, $job->pol?->code, $job->pol?->name)) {
            $base['checks'][] = 'port_cocok';
        } else {
            $blocked = true;
            $base['checks'][] = 'port_mismatch';
            $base['reasons'][] = 'Pelabuhan muat Form ('.$form->port_of_loading.') tidak cocok dengan POL Job ('.($job->pol ? $job->pol->code.' — '.$job->pol->name : 'belum diisi').').';
        }

        // Masa berlaku: tanggal filing tidak boleh lewat valid_until.
        if ($filingDate->toDateString() > $form->valid_until->toDateString()) {
            $blocked = true;
            $base['checks'][] = 'form_expired';
            $base['reasons'][] = 'Form FTA sudah kedaluwarsa (berlaku s.d. '.$form->valid_until->toDateString().', filing '.$filingDate->toDateString().').';
        } else {
            $base['checks'][] = 'masa_berlaku_ok';
        }

        $base['status'] = $blocked ? self::STATUS_DIBLOKIR : ($warned ? self::STATUS_PERINGATAN : self::STATUS_LOLOS);

        return $this->applyOverride($declaration, $base);
    }

    private function portMatches(string $formPort, ?string $polCode, ?string $polName): bool
    {
        $norm = fn (?string $v): string => strtoupper((string) preg_replace('/[^A-Z0-9]/', '', strtoupper($v ?? '')));

        $form = $norm($formPort);
        if ($form === '') {
            return false;
        }

        foreach ([$polCode, $polName] as $candidate) {
            $c = $norm($candidate);
            if ($c === '') {
                continue;
            }
            if ($form === $c || str_contains($c, $form) || str_contains($form, $c)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Blokir keras bisa diloloskan oleh override Customs Specialist yang
     * sah: ada baris override untuk (declaration, item), overrider berbeda
     * dari pembuat draf (ADR 0006), dan lampiran wajib ada.
     */
    private function applyOverride(CustomsDeclaration $declaration, array $result): array
    {
        if ($result['status'] !== self::STATUS_DIBLOKIR) {
            return $result;
        }

        $override = FtaGateOverride::query()
            ->where('customs_declaration_id', $declaration->id)
            ->where('customs_item_id', $result['customs_item_id'])
            ->first();

        if (! $override || ! $override->attachment_path) {
            return $result;
        }

        if ((int) $override->created_by_user_id === (int) $declaration->created_by_user_id) {
            return $result;
        }

        $result['status'] = self::STATUS_OVERRIDE;
        $result['checks'][] = 'override';
        $result['reasons'][] = 'Diblokir, tetapi sudah di-override oleh Customs Specialist (alasan + lampiran tercatat, audit trail).';

        return $result;
    }

    /**
     * Gerbang approve: lempar ValidationException bila ada seri yang masih
     * diblokir, atau bila deklarasi sudah diputuskan MFN tetapi masih ada
     * seri bertarif FTA.
     */
    public function assertApprovable(Job $job, CustomsDeclaration $declaration): void
    {
        if (self::declarationHasMfnDecision($declaration) || self::hasMfnDecision($job)) {
            $ftaItems = $declaration->items()->where('bm_type', 'fta')->get(['item_number', 'hs_code']);
            if ($ftaItems->isNotEmpty()) {
                $detail = $ftaItems
                    ->map(fn ($item) => "Seri {$item->item_number} (HS {$item->hs_code})")
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'approval' => "Deklarasi ini sudah diputuskan file MFN (keputusan tercatat) — {$detail} masih bertarif FTA. Hitung ulang draf tanpa klaim FTA sebelum approve; klaim ulang MFN→FTA adalah proses refund manual di luar sistem (Q19).",
                ]);
            }

            return;
        }

        $evaluation = $this->evaluate($job, $declaration);

        $blocked = collect($evaluation['items'])
            ->where('status', self::STATUS_DIBLOKIR)
            ->values();

        if ($blocked->isNotEmpty()) {
            $detail = $blocked->map(function (array $item) {
                $reasons = implode('; ', $item['reasons']);

                return "Seri {$item['item_number']} (HS {$item['hs_code']}): {$reasons}";
            })->implode(' | ');

            throw ValidationException::withMessages([
                'approval' => "Gerbang FTA memblokir filing: {$detail} Override oleh Customs Specialist (alasan + lampiran) diperlukan untuk lanjut.",
            ]);
        }
    }

    /**
     * Peringatan T5 yang diloloskan dicatat ke decision log (sekali per
     * deklarasi+seri+form) sesaat sebelum approve sukses.
     */
    public function recordProceedingWarnings(Job $job, CustomsDeclaration $declaration, array $evaluation): void
    {
        foreach ($evaluation['items'] as $item) {
            if ($item['status'] !== self::STATUS_PERINGATAN || ! in_array('digits8_warning', $item['checks'], true)) {
                continue;
            }

            FtaDecision::query()->firstOrCreate(
                [
                    'job_id' => $job->id,
                    'customs_declaration_id' => $declaration->id,
                    'customs_item_id' => $item['customs_item_id'],
                    'fta_form_id' => $item['form_id'],
                    'decision_type' => FtaDecision::TYPE_WARNING_8DIGIT,
                ],
                [
                    'company_id' => $job->company_id,
                    'reason' => implode('; ', $item['reasons']),
                    'risk_note' => 'Aturan terkunci Q19 memakai prefix 6 digit — perbedaan digit 7–8 bukan penolakan.',
                    'created_by_user_id' => auth()->id(),
                ]
            );
        }
    }
}
