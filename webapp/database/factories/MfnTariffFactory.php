<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MfnTariff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MfnTariffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'hs_code' => '8471.30.20',
            'description' => 'Komputer Portabel',
            'bm_rate' => '0.0000',
            'effective_from' => '2022-04-01',
            'verification_status' => MfnTariff::STATUS_VERIFIED,
            'legal_basis' => 'BTKI 2022',
            'created_by_user_id' => User::factory(),
            'approved_by_user_id' => null,
        ];
    }
}
