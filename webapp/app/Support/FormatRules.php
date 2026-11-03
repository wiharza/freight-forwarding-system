<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Aturan validasi format data shipping/kepabeanan (DQ-18 s.d. DQ-22).
 * Tujuan: membasmi salah ketik pada field yang menentukan uang dan hukum —
 * nomor kontainer, IMO, NPWP, kode HS, incoterm, periode Kurs Pajak.
 * Validasi ditegakkan di layer form (controller); data legacy Excel (§1.7)
 * yang masuk via model langsung tetap diterima.
 */
final class FormatRules
{
    /** Incoterms 2020 (ICC) — daftar tertutup. */
    public const INCOTERMS = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

    /** Nilai huruf ISO 6346: 10..38 dengan 11/22/33 dilewati. */
    private const ISO6346_LETTERS = [
        'A' => 10, 'B' => 12, 'C' => 13, 'D' => 14, 'E' => 15, 'F' => 16, 'G' => 17, 'H' => 18,
        'I' => 19, 'J' => 20, 'K' => 21, 'L' => 23, 'M' => 24, 'N' => 25, 'O' => 26, 'P' => 27,
        'Q' => 28, 'R' => 29, 'S' => 30, 'T' => 31, 'U' => 32, 'V' => 34, 'W' => 35, 'X' => 36,
        'Y' => 37, 'Z' => 38,
    ];

    // ------------------------------------------------------------------
    // Nomor kontainer — ISO 6346 (DQ-19)
    // ------------------------------------------------------------------

    public static function normalizeContainerNumber(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');
    }

    /** Check digit ISO 6346 (mod 11): 10 karakter pertama (4 huruf + 6 digit
     * serial) × 2⁰..2⁹, jumlah mod 11, sisa 10 → 0. Karakter ke-11 = check digit. */
    public static function iso6346CheckDigit(string $normalized): ?int
    {
        if (! preg_match('/^[A-Z]{4}\d{7}$/', $normalized)) {
            return null;
        }

        $weights = [1, 2, 4, 8, 16, 32, 64, 128, 256, 512];
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $char = $normalized[$i];
            $value = $i < 4 ? self::ISO6346_LETTERS[$char] : (int) $char;
            $sum += $value * $weights[$i];
        }

        $check = $sum % 11;

        return $check === 10 ? 0 : $check;
    }

    public static function isIso6346Valid(string $normalized): bool
    {
        if (! preg_match('/^[A-Z]{4}\d{7}$/', $normalized)) {
            return false;
        }

        return self::iso6346CheckDigit($normalized) === (int) substr($normalized, -1);
    }

    /** @return array<int, mixed> */
    public static function containerNumberRules(): array
    {
        return [
            'required', 'string', 'max:20',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $normalized = self::normalizeContainerNumber((string) $value);
                if ($normalized === '') {
                    $fail('Nomor kontainer wajib diisi.');

                    return;
                }
                if (! preg_match('/^[A-Z]{4}\d{7}$/', $normalized)) {
                    $fail('Format nomor kontainer harus ISO 6346: 4 huruf + 7 digit (mis. MSKU1234562).');

                    return;
                }
                if (! self::isIso6346Valid($normalized)) {
                    $fail('Check digit ISO 6346 nomor kontainer tidak valid — kemungkinan salah ketik.');
                }
            },
        ];
    }

    // ------------------------------------------------------------------
    // Nomor IMO (DQ-18)
    // ------------------------------------------------------------------

    /** "IMO 9074729" / "imo-9074729" / "9074729" → "9074729". */
    public static function normalizeImo(string $value): string
    {
        return strtoupper(preg_replace('/[^0-9]/', '', $value) ?? '');
    }

    public static function imoCheckDigitValid(string $sevenDigits): bool
    {
        if (! preg_match('/^\d{7}$/', $sevenDigits)) {
            return false;
        }

        $weights = [7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 6; $i++) {
            $sum += ((int) $sevenDigits[$i]) * $weights[$i];
        }

        return $sum % 10 === (int) $sevenDigits[6];
    }

    /** @return array<int, mixed> */
    public static function imoRules(): array
    {
        return [
            'nullable', 'string', 'max:15',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (trim((string) $value) === '') {
                    return;
                }
                $digits = self::normalizeImo((string) $value);
                if (! preg_match('/^\d{7}$/', $digits)) {
                    $fail('Nomor IMO harus 7 digit (boleh ditulis dengan awalan "IMO").');

                    return;
                }
                if (! self::imoCheckDigitValid($digits)) {
                    $fail('Check digit nomor IMO tidak valid — kemungkinan salah ketik.');
                }
            },
        ];
    }

    // ------------------------------------------------------------------
    // NPWP — 15 digit (PMK 24/2010) / 16 digit NIK (PMK 112/2022) (DQ-20)
    // ------------------------------------------------------------------

    public static function normalizeNpwp(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    /** @return array<int, mixed> */
    public static function npwpRules(): array
    {
        return [
            'required', 'string', 'max:30',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = self::normalizeNpwp((string) $value);
                if (! in_array(strlen($digits), [15, 16], true)) {
                    $fail('NPWP harus 15 digit (format lama) atau 16 digit (NIK, PMK 112/2022) setelah tanda baca dihapus.');
                }
            },
        ];
    }

    // ------------------------------------------------------------------
    // Kode HS — 8 digit BTKI atau 10 digit (BTKI + suffix CEISA) (DQ-20)
    // ------------------------------------------------------------------

    public static function normalizeHsCode(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    /** @return array<int, mixed> */
    public static function hsCodeRules(): array
    {
        return [
            // max 14: 10 digit + 3 titik pemisah masih diterima, dinormalisasi
            // ke digit murni sebelum disimpan
            'required', 'string', 'max:14',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = self::normalizeHsCode((string) $value);
                if (! in_array(strlen($digits), [8, 10], true)) {
                    $fail('Kode HS harus 8 digit (BTKI) atau 10 digit (BTKI + suffix CEISA), hanya angka.');
                }
            },
        ];
    }

    // ------------------------------------------------------------------
    // Incoterm (DQ-21)
    // ------------------------------------------------------------------

    /** @return array<int, mixed> */
    public static function incotermRules(): array
    {
        return ['nullable', 'string', 'size:3', Rule::in(self::INCOTERMS)];
    }
}
