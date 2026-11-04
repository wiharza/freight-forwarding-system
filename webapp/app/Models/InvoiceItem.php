<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'invoice_id',
        'job_cost_id',
        'blok',
        'description',
        'currency_code',
        'amount_valas',
        'kurs',
        'dpp',
        'ppn_rate',
        'ppn_amount',
        'total_line',
        'kena_ppn_jasa',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_valas' => 'decimal:4',
            'kurs' => 'decimal:4',
            'dpp' => 'decimal:4',
            'ppn_rate' => 'decimal:4',
            'ppn_amount' => 'decimal:4',
            'total_line' => 'decimal:4',
            'kena_ppn_jasa' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function jobCost(): BelongsTo
    {
        return $this->belongsTo(JobCost::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}