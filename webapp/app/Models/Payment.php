<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToCompany, HasFactory;

    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_KAS = 'kas';
    public const METHOD_UANG_MUKA = 'uang_muka';

    protected $fillable = [
        'invoice_id',
        'importir_id',
        'payment_date',
        'amount',
        'method',
        'reference',
        'is_unallocated',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:4',
            'is_unallocated' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function importir(): BelongsTo
    {
        return $this->belongsTo(Importir::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}