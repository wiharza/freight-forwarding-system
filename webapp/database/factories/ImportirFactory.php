<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Importir;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Importir>
 */
class ImportirFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->company(),
            'npwp' => $this->faker->unique()->numerify('##.###.###.#-###.###'),
            'nib' => $this->faker->numerify('#############'),
            'status_api' => 'API-U',
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'pic_name' => $this->faker->name(),
        ];
    }
}
