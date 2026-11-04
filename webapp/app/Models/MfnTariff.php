<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Audit;
use App\Support\MakerChecker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfnTariff extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_VERIFIED = 'terverifikasi';

    public const STATUS_UNVERIFIED = 'perlu_verifikasi';

    protected $fillable = [
        'hs_code',
        'description',
        'bm_rate',
        'effective_from',
        'effective_to',
        'verification_status',
        'legal_basis',
        'created_by_user_id',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'bm_rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
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

    public function approve(User $approver, string $legalBasis): void
    {
        MakerChecker::assertApproverDiffers($approver, $this);

        $this->update([
            'verification_status' => self::STATUS_VERIFIED,
            'approved_by_user_id' => $approver->id,
            'legal_basis' => $legalBasis,
        ]);

        Audit::record('mfn_tariff.approved', $this, [
            'verification_status' => self::STATUS_VERIFIED,
            'approved_by' => $approver->id,
            'legal_basis' => $legalBasis,
        ], 'Persetujuan tarif MFN resmi');
    }
}
