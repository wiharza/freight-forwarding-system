<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => $this->faker->unique()->currencyCode(),
            'name' => $this->faker->word(),
            'is_active' => false,
            'effective_date' => null,
        ];
    }
}
