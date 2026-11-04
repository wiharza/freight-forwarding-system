<?php

namespace App\Services;

use App\Models\FtaTariff;
use App\Models\LuxuryGood;
use App\Models\MfnTariff;
use App\Models\Pph22Rule;
use App\Models\TaxExchangeRate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class PibCalculator
{
    /**
     * Pembulatan statutori statut kepabeanan Indonesia (§3.4, KMK 584/KMK.05/1996):
     * Dibulatkan ke atas dalam ribuan penuh per komponen per seri barang.
     */
    public static function roundUpToThousand(float|string $amount): float
    {
        $val = (float) $amount;
        if ($val <= 0) {
            return 0.0;
        }

        return (float) (ceil(round($val, 2) / 1000) * 1000);
    }

    /**
     * Samakan kode HS ke digit murni untuk pencocokan tarif (BUGFIX 2026-11-04:
     * tarif master tersimpan bertitik, mis. '3926.90.99', sedangkan input
     * sudah dinormalisasi ke digit murni '39269099' oleh
     * FormatRules::normalizeHsCode — where('hs_code', $digits) tidak pernah
     * cocok sehingga BM selalu 0. Bandingkan digit lawan digit di kedua sisi;
     * untuk HS 10 digit (BTKI + suffix CEISA) coba juga prefix 8 digit,
     * karena suffix CEISA tidak mengubah tarif BTKI).
     */
    private static function applyHsMatch($query, string $hsDigits): void
    {
        $query->where(function ($q) use ($hsDigits) {
            $q->whereRaw("REPLACE(REPLACE(hs_code, '.', ''), '-', '') = ?", [$hsDigits]);
            if (strlen($hsDigits) === 10) {
                $q->orWhereRaw("REPLACE(REPLACE(hs_code, '.', ''), '-', '') = ?", [substr($hsDigits, 0, 8)]);
            }
        });
    }

    /**
     * Hitung satu baris Seri Barang PIB (§3.2, Q28, Q30, Q32).
     */
    public function calculateItem(array $input, string $statusApi = 'API-U', ?string $ftaScheme = null, ?CarbonInterface $filingDate = null): array
    {
        $hsCode = preg_replace('/[^0-9]/', '', trim($input['hs_code'])) ?? '';
        $fobValas = (float) ($input['fob_valas'] ?? 0);
        $freightValas = (float) ($input['freight_valas'] ?? 0);
        $currencyCode = strtoupper($input['currency_code'] ?? 'USD');
        $filingDate = $filingDate ? Carbon::parse($filingDate) : now();

        // 1. Asuransi (§3.2, PMK 144/2022)
        $insuranceBasis = $input['insurance_basis'] ?? 'input_riil';
        $insuranceValas = (float) ($input['insurance_valas'] ?? 0);

        if ($insuranceBasis === 'dalam_negeri_0') {
            $insuranceValas = 0.0;
        } elseif ($insuranceBasis === 'fallback_fob_0.5') {
            $insuranceValas = round($fobValas * 0.005, 4);
        } elseif ($insuranceBasis === 'fallback_cfr_0.5') {
            $insuranceValas = round(($fobValas + $freightValas) * 0.005, 4);
        }

        // 2. Kurs Pajak Kemenkeu (NDPBM - Rabu s.d Selasa)
        // TEMUAN #4 (2026-11-04): kurs yang hilang TIDAK BOLEH diam-diam
        // menjadi 1.0 — untuk valas itu meruntuhkan CIF Rupiah (dan semua
        // pungutan turunannya, mis. CIF USD 50.000 × 1,0 = Rp 50.000) tanpa
        // jejak. IDR = 1.0 menurut definisi; valas tanpa baris Kurs Pajak
        // untuk periode itu = gagal dengan pesan yang jelas.
        $kursPajakRate = (float) ($input['kurs_pajak_rate'] ?? 0);
        if ($kursPajakRate <= 0) {
            if ($currencyCode === 'IDR') {
                $kursPajakRate = 1.0;
            } else {
                $taxRate = TaxExchangeRate::resolveRate($currencyCode, $filingDate);
                if (! $taxRate) {
                    throw ValidationException::withMessages([
                        'kurs_pajak_rate' => "Kurs Pajak {$currencyCode} tidak tersedia untuk tanggal {$filingDate->toDateString()} — isi Kurs Pajak (KMK) terlebih dahulu sebelum menghitung PIB.",
                    ]);
                }
                $kursPajakRate = (float) $taxRate->rate_to_idr;
            }
        }

        // 3. CIF Valas & CIF Rupiah (presisi penuh tanpa pembulatan prematur, Lampiran E)
        $cifValas = round($fobValas + $freightValas + $insuranceValas, 4);
        $cifRupiah = round($cifValas * $kursPajakRate, 4);

        // 4. Bea Masuk (BM)
        $bmType = 'mfn';
        $bmRate = 0.0;
        $isUnverifiedWarning = false;

        if ($ftaScheme) {
            $ftaTariff = FtaTariff::query()
                ->where('scheme_code', $ftaScheme);
            self::applyHsMatch($ftaTariff, $hsCode);
            $ftaTariff = $ftaTariff
                ->where('effective_from', '<=', $filingDate->toDateString())
                ->where(function ($q) use ($filingDate) {
                    $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $filingDate->toDateString());
                })
                ->first();

            if ($ftaTariff) {
                $bmType = 'fta';
                $bmRate = (float) $ftaTariff->fta_rate;
                if ($ftaTariff->verification_status === FtaTariff::STATUS_UNVERIFIED) {
                    $isUnverifiedWarning = true;
                }
            }
        }

        if ($bmType === 'mfn') {
            $mfnTariff = MfnTariff::query();
            self::applyHsMatch($mfnTariff, $hsCode);
            $mfnTariff = $mfnTariff
                ->where('effective_from', '<=', $filingDate->toDateString())
                ->where(function ($q) use ($filingDate) {
                    $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $filingDate->toDateString());
                })
                ->first();

            if ($mfnTariff) {
                $bmRate = (float) $mfnTariff->bm_rate;
                if ($mfnTariff->verification_status === MfnTariff::STATUS_UNVERIFIED) {
                    $isUnverifiedWarning = true;
                }
            } else {
                $bmRate = (float) ($input['bm_rate'] ?? 0);
            }
        }

        $bmAmount = self::roundUpToThousand($cifRupiah * $bmRate);

        // 5. Nilai Impor = CIF Rupiah + BM + Pungutan Lain (§3.2, Q28)
        $pungutanLain = (float) ($input['pungutan_lain_amount'] ?? 0);
        $nilaiImpor = round($cifRupiah + $bmAmount + $pungutanLain, 4);

        // 6. PPN Impor (tarif formal 12%, barang umum DPP 11/12 -> efektif 11%)
        $luxuryCheck = LuxuryGood::checkHs($hsCode, $cifValas);
        $isPpnLuxury = (bool) ($input['is_ppn_luxury'] ?? $luxuryCheck['is_ppn_luxury']);

        if ($isPpnLuxury) {
            $dppPpn = $nilaiImpor; // 12% penuh
        } else {
            $dppPpn = round($nilaiImpor * (11 / 12), 4); // DPP Nilai Lain 11/12
        }

        $ppnAmount = self::roundUpToThousand($dppPpn * 0.12);

        // 7. PPh 22 Impor (PMK 51/2025 - urutan terkunci §3.2)
        $isPphExempt = (bool) ($input['is_pph22_exempt'] ?? false);
        $pphCategory = 'umum';
        $pphRate = 0.025; // default API

        if ($isPphExempt) {
            $pphCategory = 'bebas_pasal_4';
            $pphRate = 0.0;
        } else {
            $hasApi = in_array(strtoupper($statusApi), ['API-U', 'API-P', 'API']);
            $rule = Pph22Rule::matchHs($hsCode);

            // ADR 0008: aturan PPh 22 yang masih Perlu Verifikasi ikut
            // menandai item ini — approve Snapshot wajib memblokirnya
            // (sama seperti tarif MFN/FTA yang belum terverifikasi).
            if ($rule && $rule->verification_status === Pph22Rule::STATUS_UNVERIFIED) {
                $isUnverifiedWarning = true;
            }

            if ($rule) {
                if ($rule->lampiran === 'A') {
                    $pphCategory = 'lampiran_a_10';
                    $pphRate = 0.1000; // 10% dengan / tanpa API
                } elseif ($rule->lampiran === 'B') {
                    $pphCategory = 'lampiran_b_7.5';
                    $pphRate = 0.0750; // 7.5% dengan / tanpa API
                } elseif ($rule->lampiran === 'C') {
                    // Komoditas khusus (kedelai, gandum, tepung terigu)
                    $pphCategory = 'kedelai_gandum_0.5';
                    $pphRate = $hasApi ? 0.0050 : 0.0750;
                } elseif ($rule->lampiran === 'D') {
                    // TEMUAN #5 (2026-11-04): emas batangan 0,25% (PMK
                    // 51/2025 Pasal 3 ayat (1), HS 71.08, Lampiran D seed)
                    // dengan atau tanpa API. Sebelumnya cabang ini tidak ada
                    // sehingga emas batangan jatuh ke tarif umum 2,5% (10x).
                    $pphCategory = 'emas_batangan_0.25';
                    $pphRate = 0.0025;
                }
                // Catatan: barang lelang (7,5% dari HARGA JUAL LELANG, PMK
                // 51/2025) sengaja belum ditangani — basisnya bukan Nilai
                // Impor dan model belum punya field harga lelang.
            } else {
                // Kategori umum: 2.5% dengan API, 7.5% tanpa API
                if ($hasApi) {
                    $pphCategory = 'umum_api_2.5';
                    $pphRate = 0.0250;
                } else {
                    $pphCategory = 'umum_non_api_7.5';
                    $pphRate = 0.0750;
                }
            }
        }

        $pphAmount = self::roundUpToThousand($nilaiImpor * $pphRate);

        // 8. PPnBM Impor (PP 73/2019 + PMK 96/2021)
        $ppnbmRate = 0.0;
        $ppnbmAmount = 0.0;

        if ($luxuryCheck['has_ppnbm']) {
            $ppnbmRate = $luxuryCheck['ppnbm_rate'];
            $ppnbmAmount = self::roundUpToThousand($nilaiImpor * $ppnbmRate);
        }

        // 9. Total Pungutan Seri Barang = BM + PPN + PPh22 + PPnBM + Pungutan Lain
        $totalItemLevy = $bmAmount + $ppnAmount + $pphAmount + $ppnbmAmount + $pungutanLain;

        return [
            'hs_code' => $hsCode,
            'description' => $input['description'] ?? null,
            'currency_code' => $currencyCode,
            'fob_valas' => $fobValas,
            'freight_valas' => $freightValas,
            'insurance_valas' => $insuranceValas,
            'insurance_basis' => $insuranceBasis,
            'kurs_pajak_rate' => $kursPajakRate,
            'cif_valas' => $cifValas,
            'cif_rupiah' => $cifRupiah,
            'bm_type' => $bmType,
            'bm_rate' => $bmRate,
            'bm_amount' => $bmAmount,
            'nilai_impor' => $nilaiImpor,
            'is_ppn_luxury' => $isPpnLuxury,
            'dpp_ppn' => $dppPpn,
            'ppn_rate' => 0.1200,
            'ppn_amount' => $ppnAmount,
            'pph22_category' => $pphCategory,
            'pph22_rate' => $pphRate,
            'pph22_amount' => $pphAmount,
            'is_pph22_exempt' => $isPphExempt,
            'exemption_reason' => $input['exemption_reason'] ?? null,
            'ppnbm_rate' => $ppnbmRate,
            'ppnbm_amount' => $ppnbmAmount,
            'pungutan_lain_amount' => $pungutanLain,
            'total_item_levy' => $totalItemLevy,
            'is_unverified_warning' => $isUnverifiedWarning,
        ];
    }

    /**
     * Hitung total draf PIB lengkap dari array Seri Barang.
     */
    public function calculateDeclaration(array $itemsInput, string $statusApi = 'API-U', ?string $ftaScheme = null, ?CarbonInterface $filingDate = null): array
    {
        $calculatedItems = [];
        $totalCifValas = 0.0;
        $totalCifIdr = 0.0;
        $totalBm = 0.0;
        $totalPpn = 0.0;
        $totalPph22 = 0.0;
        $totalPpnbm = 0.0;
        $totalPungutanLain = 0.0;
        $hasUnverified = false;

        $itemNumber = 1;
        foreach ($itemsInput as $raw) {
            $res = $this->calculateItem($raw, $statusApi, $ftaScheme, $filingDate);
            $res['item_number'] = $itemNumber++;
            $calculatedItems[] = $res;

            $totalCifValas += $res['cif_valas'];
            $totalCifIdr += $res['cif_rupiah'];
            $totalBm += $res['bm_amount'];
            $totalPpn += $res['ppn_amount'];
            $totalPph22 += $res['pph22_amount'];
            $totalPpnbm += $res['ppnbm_amount'];
            $totalPungutanLain += $res['pungutan_lain_amount'];

            if ($res['is_unverified_warning']) {
                $hasUnverified = true;
            }
        }

        $totalPungutan = $totalBm + $totalPpn + $totalPph22 + $totalPpnbm + $totalPungutanLain;

        return [
            'total_cif_valas' => round($totalCifValas, 4),
            'total_cif_idr' => round($totalCifIdr, 4),
            'total_bm' => $totalBm,
            'total_ppn' => $totalPpn,
            'total_pph22' => $totalPph22,
            'total_ppnbm' => $totalPpnbm,
            'total_pungutan_lain' => $totalPungutanLain,
            'total_pungutan' => $totalPungutan,
            'items' => $calculatedItems,
            'has_unverified_warning' => $hasUnverified,
        ];
    }
}
