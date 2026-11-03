<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportirChecklistOverride extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'importir_id',
        'document_type',
        'is_required',
        'reason',
        'approved_by_user_id',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'effective_date' => 'date',
        ];
    }

    public function importir(): BelongsTo
    {
        return $this->belongsTo(Importir::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
