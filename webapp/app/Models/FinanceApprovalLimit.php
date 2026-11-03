<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceApprovalLimit extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'user_id',
        'limit_amount',
        'unlimited',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'limit_amount' => 'decimal:4',
            'unlimited' => 'boolean',
            'effective_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}