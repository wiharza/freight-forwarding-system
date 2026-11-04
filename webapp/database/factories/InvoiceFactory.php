<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Importir;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        static $number = 0;
        $number++;

        return [
            'company_id' => Company::factory(),
            'job_id' => Job::factory(),
            'importir_id' => Importir::factory(),
            'invoice_number' => 'INV-2026-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'total_invoice' => 10000000.0000,
            'total_paid' => 0,
            'created_by_user_id' => User::factory(),
        ];
    }
}
