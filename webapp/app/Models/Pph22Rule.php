<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pph22Rule extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_VERIFIED = 'terverifikasi';
    public const STATUS_UNVERIFIED = 'perlu_verifikasi';

    protected $fillable = [
        'hs_code',
        'lampiran',
        'rate',
        'notes',
        'verification_status',
        'legal_basis',
        'created_by_user_id',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Cari aturan tarif PPh 22 berdasarkan kode HS (prefix matching 4 atau 8 digit)
     * Mengembalikan ?Pph22Rule
     */
    public static function matchHs(string $hsCode): ?self
    {
        $cleanHs = str_replace(['.', ' ', '-'], '', trim($hsCode));

        // 1. Coba exact match
        $rule = self::query()
            ->where('hs_code', $hsCode)
            ->first();

        if ($rule) {
            return $rule;
        }

        // 2. Coba prefix 4 digit (pos tarif Bab.Pos, e.g. 33.04 -> 3304)
        $prefix4 = substr($cleanHs, 0, 4);
        $dotPrefix4 = substr($cleanHs, 0, 2) . '.' . substr($cleanHs, 2, 2);

        return self::query()
            ->where(function ($q) use ($prefix4, $dotPrefix4) {
                $q->where('hs_code', $dotPrefix4)
                  ->orWhere('hs_code', $prefix4);
            })
            ->first();
    }
}
