<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LineTariff;
use App\Models\ShippingLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LineTariff>
 */
class LineTariffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'shipping_line_id' => ShippingLine::factory(),
            'charge_type' => LineTariff::TYPE_DEMURRAGE,
            'container_size' => '20',
            'day_from' => 1,
            'day_to' => 3,
            'currency_code' => 'USD',
            'rate_per_day' => '35.0000',
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => null,
            'status' => LineTariff::STATUS_ACTIVE,
            'created_by_user_id' => User::factory(),
            'approved_by_user_id' => null,
            'notes' => null,
        ];
    }
}
