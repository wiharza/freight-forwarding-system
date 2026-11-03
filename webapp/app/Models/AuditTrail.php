<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * §1.5 / ADR 0006 — audit trail append-only: tidak pernah diedit, tidak pernah dihapus.
 */
class AuditTrail extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'object_type',
        'object_id',
        'changes',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit trail bersifat append-only dan tidak dapat diedit.');
        });

        static::deleting(function () {
            throw new LogicException('Audit trail tidak dapat dihapus (arsip deactivate-only, §1.5).');
        });
    }
}
