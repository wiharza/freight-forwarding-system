<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('ID???')),
            'name' => $this->faker->city().' Port',
            'type' => Location::TYPE_PORT,
            'city' => $this->faker->city(),
        ];
    }
}
