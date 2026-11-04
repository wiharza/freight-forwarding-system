<?php

namespace Database\Seeders;

use App\Models\FtaTariff;
use App\Models\LuxuryGood;
use App\Models\MfnTariff;
use App\Models\Pph22Rule;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

class LegalTariffSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = app(CompanyContext::class)->id();

        // 1. Seed Mapping PPh 22 dari file seed-pph22-lampiran.csv (215 baris resmi PMK 51/2025)
        $csvPath = database_path('data/seed-pph22-lampiran.csv');
        if (! file_exists($csvPath)) {
            $csvPath = base_path('../docs/research/seed-pph22-lampiran.csv');
        }

        if (file_exists($csvPath)) {
            $handle = fopen($csvPath, 'r');
            $header = fgetcsv($handle); // "hs","lampiran","tarif","status_verifikasi"

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 3) {
                    $hs = trim($row[0]);
                    $lampiran = trim($row[1]);
                    $rawRate = trim($row[2]); // e.g. "10%" atau "7,5%" atau "0.5%"
                    $clean = str_replace(['%', ','], ['', '.'], $rawRate);
                    $decimalRate = is_numeric($clean) ? ((float) $clean / 100) : 0.0750;

                    Pph22Rule::query()->firstOrCreate(
                        ['company_id' => $companyId, 'hs_code' => $hs],
                        [
                            'lampiran' => $lampiran,
                            'rate' => $decimalRate,
                            'verification_status' => Pph22Rule::STATUS_VERIFIED,
                            'legal_basis' => 'PMK 51/2025 Lampiran '.$lampiran,
                        ]
                    );
                }
            }
            fclose($handle);
        }

        // 2. Sample pos tarif MFN umum (BTKI 2022)
        $mfnSamples = [
            ['hs' => '8471.30.20', 'desc' => 'Laptop / Komputer Portabel', 'rate' => 0.0000, 'legal' => 'BTKI 2022 Pos 84.71'],
            ['hs' => '8703.23.51', 'desc' => 'Mobil Penumpang Sedan > 1500cc sd 2500cc', 'rate' => 0.5000, 'legal' => 'BTKI 2022 Pos 87.03'],
            ['hs' => '3926.90.99', 'desc' => 'Barang Plastik Lainnya', 'rate' => 0.1000, 'legal' => 'BTKI 2022 Pos 39.26'],
            ['hs' => '1001.99.12', 'desc' => 'Gandum Meslin', 'rate' => 0.0000, 'legal' => 'BTKI 2022 Pos 10.01'],
        ];

        foreach ($mfnSamples as $m) {
            MfnTariff::query()->firstOrCreate(
                ['company_id' => $companyId, 'hs_code' => $m['hs']],
                [
                    'description' => $m['desc'],
                    'bm_rate' => $m['rate'],
                    'effective_from' => '2022-04-01',
                    'verification_status' => MfnTariff::STATUS_VERIFIED,
                    'legal_basis' => $m['legal'],
                ]
            );
        }

        // 3. Sample Tarif FTA Preferensi (9 skema aktif)
        $ftaSamples = [
            ['scheme' => 'ACFTA', 'hs' => '8471.30.20', 'rate' => 0.0000, 'coo' => 'Form E'],
            ['scheme' => 'ACFTA', 'hs' => '3926.90.99', 'rate' => 0.0500, 'coo' => 'Form E'],
            ['scheme' => 'ATIGA', 'hs' => '8703.23.51', 'rate' => 0.0000, 'coo' => 'Form D'],
            ['scheme' => 'AKFTA', 'hs' => '8471.30.20', 'rate' => 0.0000, 'coo' => 'Form AK'],
            ['scheme' => 'IA-CEPA', 'hs' => '1001.99.12', 'rate' => 0.0000, 'coo' => 'Form IA-CEPA'],
        ];

        foreach ($ftaSamples as $f) {
            FtaTariff::query()->firstOrCreate(
                ['company_id' => $companyId, 'scheme_code' => $f['scheme'], 'hs_code' => $f['hs']],
                [
                    'fta_rate' => $f['rate'],
                    'coo_type' => $f['coo'],
                    'effective_from' => '2023-01-01',
                    'effective_to' => '2027-12-31',
                    'verification_status' => FtaTariff::STATUS_VERIFIED,
                    'legal_basis' => 'PMK 88/2023 Skema '.$f['scheme'],
                ]
            );
        }

        // 4. Sample Barang Mewah (PPN 12% & PPnBM ambang USD 30k / 50k)
        $luxurySamples = [
            [
                'hs' => '8703',
                'cat' => 'kendaraan',
                'is_ppn' => true,
                'has_ppnbm' => true,
                'ppnbm_rate' => 0.2000,
                'threshold' => 30000.0000, // NI > USD 30.000 (PV-6, PP 73/2019)
                'legal' => 'PP 73/2019 jo. PMK 96/2021',
            ],
            [
                'hs' => '8903',
                'cat' => 'pesawat_kapal',
                'is_ppn' => true,
                'has_ppnbm' => true,
                'ppnbm_rate' => 0.7500,
                'threshold' => 50000.0000, // NI > USD 50.000 (PV-6, PP 73/2019)
                'legal' => 'PP 73/2019 jo. PMK 96/2021',
            ],
        ];

        foreach ($luxurySamples as $lx) {
            LuxuryGood::query()->firstOrCreate(
                ['company_id' => $companyId, 'hs_code' => $lx['hs']],
                [
                    'category' => $lx['cat'],
                    'is_ppn_luxury' => $lx['is_ppn'],
                    'has_ppnbm' => $lx['has_ppnbm'],
                    'ppnbm_rate' => $lx['ppnbm_rate'],
                    'import_value_threshold_usd' => $lx['threshold'],
                    'verification_status' => LuxuryGood::STATUS_VERIFIED,
                    'legal_basis' => $lx['legal'],
                ]
            );
        }
    }
}
