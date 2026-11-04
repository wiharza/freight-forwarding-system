<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuxuryGood extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_VERIFIED = 'terverifikasi';

    public const STATUS_UNVERIFIED = 'perlu_verifikasi';

    protected $fillable = [
        'hs_code',
        'category',
        'is_ppn_luxury',
        'has_ppnbm',
        'ppnbm_rate',
        'import_value_threshold_usd',
        'verification_status',
        'legal_basis',
        'created_by_user_id',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_ppn_luxury' => 'boolean',
            'has_ppnbm' => 'boolean',
            'ppnbm_rate' => 'decimal:4',
            'import_value_threshold_usd' => 'decimal:4',
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
     * Cek apakah HS masuk kategori barang mewah PPN atau PPnBM (§3.2, PV-6)
     */
    public static function checkHs(string $hsCode, float $importValueUsd = 0): array
    {
        $cleanHs = str_replace(['.', ' ', '-'], '', trim($hsCode));

        $item = self::query()
            ->where('hs_code', $hsCode)
            ->orWhere('hs_code', substr($cleanHs, 0, 4))
            ->first();

        if (! $item) {
            return [
                'is_ppn_luxury' => false,
                'has_ppnbm' => false,
                'ppnbm_rate' => 0.0,
                'model' => null,
            ];
        }

        $exceedsThreshold = true;
        if ($item->import_value_threshold_usd !== null) {
            $exceedsThreshold = $importValueUsd > (float) $item->import_value_threshold_usd;
        }

        return [
            'is_ppn_luxury' => (bool) $item->is_ppn_luxury,
            'has_ppnbm' => $item->has_ppnbm && $exceedsThreshold,
            'ppnbm_rate' => $item->has_ppnbm && $exceedsThreshold ? (float) $item->ppnbm_rate : 0.0,
            'model' => $item,
        ];
    }
}
