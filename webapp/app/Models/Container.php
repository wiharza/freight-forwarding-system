<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Container extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'job_id',
        'container_number',
        'size',
        'type',
        'seal_number',
        'gross_weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'gross_weight_kg' => 'decimal:4',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
