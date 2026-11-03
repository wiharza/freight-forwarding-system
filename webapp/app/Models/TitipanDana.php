<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TitipanDana extends Model
{
    use BelongsToCompany, HasFactory;

    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_KAS = 'kas';

    protected $fillable = [
        'importir_id',
        'received_date',
        'amount',
        'allocated_amount',
        'method',
        'reference',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'allocated_amount' => 'decimal:4',
            'received_date' => 'date',
        ];
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
        return $this->hasMany(TitipanDanaAllocation::class);
    }

    public function sisaSaldo(): float
    {
        return (float) $this->amount - (float) $this->allocated_amount;
    }
}