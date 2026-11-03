<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Job;
use App\Models\Trip;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'job_id' => Job::factory(),
            'vendor_id' => Vendor::factory(),
            'trip_number' => 'TRP-' . $this->faker->unique()->numerify('######'),
            'scheduled_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'status' => 'assigned',
            'driver_name' => $this->faker->name(),
            'driver_phone' => $this->faker->phoneNumber(),
            'vehicle_number' => $this->faker->bothify('?? #### ??'),
        ];
    }
}