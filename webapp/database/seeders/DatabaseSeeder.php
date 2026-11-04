<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyContext;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['name' => 'PPJK Induk'],
        );

        // HARDENING 2026-11-04: kunci tenant demo secara eksplisit SEBELUM
        // memanggil sub-seeder. MasterDataSeeder dan LegalTariffSeeder
        // menentukan tenant lewat CompanyContext::id(), yang bila belum
        // di-set fallback-nya adalah company dengan id TERENDAH (min('id')).
        // Bila database sudah berisi company lain dengan id lebih rendah,
        // data demo mendarat di tenant yang salah — sementara user demo di
        // atas dibuat di 'PPJK Induk'. Dengan set() ini, seluruh seed demo
        // dijamin satu tenant dengan user-nya, apa pun isi database sebelumnya.
        app(CompanyContext::class)->set($company->id);

        // 6 peran kanonik (§2.5).
        $roles = collect([
            [Role::ADMIN, 'Admin'],
            [Role::CS, 'Customer Service'],
            [Role::CUSTOMS_SPECIALIST, 'Customs Specialist'],
            [Role::OPS, 'Ops'],
            [Role::FIELD, 'Field'],
            [Role::FINANCE, 'Finance'],
        ])->mapWithKeys(function (array $role): array {
            $model = Role::query()->firstOrCreate(
                ['slug' => $role[0]],
                ['name' => $role[1]],
            );

            return [$role[0] => $model];
        });

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.local'],
            [
                'company_id' => $company->id,
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ],
        );

        $admin->roles()->sync([$roles[Role::ADMIN]->id]);

        // Budi (CS) — dipakai smoke test E2E Playwright (tests/e2e/smoke.spec.cjs
        // login sebagai budi@example.local). Seeder awalnya hanya membuat admin,
        // sehingga test gagal di langkah login.
        $budi = User::query()->firstOrCreate(
            ['email' => 'budi@example.local'],
            [
                'company_id' => $company->id,
                'name' => 'Budi',
                'password' => Hash::make('password'),
            ],
        );

        $budi->roles()->sync([$roles[Role::CS]->id]);

        $this->call(MasterDataSeeder::class);
        $this->call(LegalTariffSeeder::class);
    }
}
