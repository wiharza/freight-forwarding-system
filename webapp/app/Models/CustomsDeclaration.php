<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomsDeclaration extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FILED = 'filed';
    public const STATUS_STALE = 'stale';

    protected $fillable = [
        'job_id',
        'pib_number',
        'version',
        'status',
        'kmk_number',
        'total_cif_valas',
        'total_cif_idr',
        'total_bm',
        'total_ppn',
        'total_pph22',
        'total_ppnbm',
        'total_pungutan_lain',
        'total_pungutan',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cif_valas' => 'decimal:4',
            'total_cif_idr' => 'decimal:4',
            'total_bm' => 'decimal:4',
            'total_ppn' => 'decimal:4',
            'total_pph22' => 'decimal:4',
            'total_ppnbm' => 'decimal:4',
            'total_pungutan_lain' => 'decimal:4',
            'total_pungutan' => 'decimal:4',
            'approved_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomsItem::class)->orderBy('item_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
