<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UangMuka extends Model
{
    use BelongsToCompany, HasFactory;

    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_KAS = 'kas';
    public const METHOD_BILLING_CODE_LANGSUNG = 'billing_code_langsung';

    protected $fillable = [
        'job_id',
        'amount',
        'received_date',
        'method',
        'reference',
        'settled_amount',
        'is_settled',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'settled_amount' => 'decimal:4',
            'received_date' => 'date',
            'is_settled' => 'boolean',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function settle(float $amount): void
    {
        $this->update([
            'settled_amount' => $this->settled_amount + $amount,
            'is_settled' => ($this->settled_amount + $amount) >= $this->amount,
        ]);
    }
}