<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SuratJalan;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SuratJalan>
 */
class SuratJalanFactory extends Factory
{
    protected $model = SuratJalan::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trip_id' => Trip::factory(),
            'sj_number' => 'SJ-' . $this->faker->unique()->numerify('######'),
            'sj_date' => $this->faker->date(),
            'driver_name' => $this->faker->name(),
            'driver_phone' => $this->faker->phoneNumber(),
            'vehicle_number' => $this->faker->bothify('?? #### ??'),
            'origin_address' => $this->faker->address(),
            'destination_address' => $this->faker->address(),
            'status' => 'draft',
            'created_by_user_id' => null,
            'signed_by_user_id' => null,
        ];
    }
}