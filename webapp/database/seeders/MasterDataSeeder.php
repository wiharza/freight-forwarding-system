<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ContainerType;
use App\Models\Currency;
use App\Models\Location;
use App\Models\ShippingLine;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = app(CompanyContext::class)->id();

        // 1. Mata Uang (§6.6, Q24): v1 hanya USD aktif
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'is_active' => true, 'effective_date' => now()->toDateString()],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'is_active' => false, 'effective_date' => null],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'is_active' => false, 'effective_date' => null],
            ['code' => 'EUR', 'name' => 'Euro', 'is_active' => false, 'effective_date' => null],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'is_active' => false, 'effective_date' => null],
        ];

        foreach ($currencies as $curr) {
            Currency::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $curr['code']],
                $curr
            );
        }

        // 2. Jenis Kontainer (§2.1, Q10)
        $containerTypes = [
            ['size' => '20', 'type' => 'Dry', 'description' => "20' General Purpose / Dry Container"],
            ['size' => '40', 'type' => 'Dry', 'description' => "40' General Purpose / Dry Container"],
            ['size' => '40', 'type' => 'High Cube', 'description' => "40' High Cube Container"],
            ['size' => '20', 'type' => 'Reefer', 'description' => "20' Refrigerated Container"],
            ['size' => '40', 'type' => 'Reefer', 'description' => "40' Refrigerated Container"],
        ];

        foreach ($containerTypes as $ct) {
            ContainerType::query()->firstOrCreate(
                ['company_id' => $companyId, 'size' => $ct['size'], 'type' => $ct['type']],
                $ct
            );
        }

        // 3. Pelabuhan Utama Indonesia
        $locations = [
            ['code' => 'IDTPP', 'name' => 'Tanjung Priok, Jakarta', 'type' => Location::TYPE_PORT, 'city' => 'Jakarta'],
            ['code' => 'IDTPS', 'name' => 'Tanjung Perak, Surabaya', 'type' => Location::TYPE_PORT, 'city' => 'Surabaya'],
            ['code' => 'IDTPM', 'name' => 'Tanjung Emas, Semarang', 'type' => Location::TYPE_PORT, 'city' => 'Semarang'],
            ['code' => 'IDBLW', 'name' => 'Belawan, Medan', 'type' => Location::TYPE_PORT, 'city' => 'Medan'],
        ];

        foreach ($locations as $loc) {
            Location::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $loc['code'], 'type' => $loc['type']],
                $loc
            );
        }

        // 4. Shipping Line Contoh
        $lines = [
            ['code' => 'MSK', 'name' => 'Maersk Line'],
            ['code' => 'SITC', 'name' => 'SITC Container Lines'],
            ['code' => 'ONE', 'name' => 'Ocean Network Express (ONE)'],
            ['code' => 'MSC', 'name' => 'Mediterranean Shipping Company (MSC)'],
            ['code' => 'EMC', 'name' => 'Evergreen Marine Corp'],
        ];

        foreach ($lines as $line) {
            ShippingLine::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $line['code']],
                $line
            );
        }
    }
}
