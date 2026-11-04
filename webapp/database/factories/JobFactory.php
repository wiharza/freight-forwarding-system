<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected static int $sequence = 0;

    public function definition(): array
    {
        self::$sequence++;

        return [
            'company_id' => Company::factory(),
            'job_number' => 'JOB-'.str_pad((string) self::$sequence, 6, '0', STR_PAD_LEFT),
        ];
    }
}
