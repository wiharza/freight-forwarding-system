<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
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

        $this->call(MasterDataSeeder::class);
        $this->call(LegalTariffSeeder::class);
    }
}
