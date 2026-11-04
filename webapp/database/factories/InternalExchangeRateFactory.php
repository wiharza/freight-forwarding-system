<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InternalExchangeRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalExchangeRate>
 */
class InternalExchangeRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'rate_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'rate_to_idr' => '15800.0000',
            'cutoff_time' => '09:00:00',
            'is_locked' => false,
            'created_by_user_id' => User::factory(),
        ];
    }
}
