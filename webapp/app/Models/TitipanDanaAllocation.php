<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitipanDanaAllocation extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'titipan_dana_id',
        'job_id',
        'job_cost_id',
        'invoice_id',
        'amount',
        'reason',
        'allocated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function titipanDana(): BelongsTo
    {
        return $this->belongsTo(TitipanDana::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function jobCost(): BelongsTo
    {
        return $this->belongsTo(JobCost::class);
    }

    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by_user_id');
    }
}