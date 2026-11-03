<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ShippingLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShippingLine>
 */
class ShippingLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->company().' Shipping',
            'free_days_demurrage' => 7,
            'free_days_detention' => 7,
            'start_offset_demurrage' => 'H+1',
            'start_offset_detention' => 'H+1',
            'calendar_type_demurrage' => 'calendar',
            'calendar_type_detention' => 'calendar',
            'mode' => 'terpisah',
        ];
    }
}
