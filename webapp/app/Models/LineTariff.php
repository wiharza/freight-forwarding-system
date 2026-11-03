<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Audit;
use App\Support\MakerChecker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineTariff extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPE_DEMURRAGE = 'demurrage';
    public const TYPE_DETENTION = 'detention';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'shipping_line_id',
        'charge_type',
        'container_size',
        'day_from',
        'day_to',
        'currency_code',
        'rate_per_day',
        'effective_from',
        'effective_to',
        'status',
        'created_by_user_id',
        'approved_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_day' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'day_from' => 'integer',
            'day_to' => 'integer',
        ];
    }

    public function shippingLine(): BelongsTo
    {
        return $this->belongsTo(ShippingLine::class);
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
     * Setujui tarif (Maker-Checker per user ID, ADR 0006).
     */
    public function approve(User $approver, ?string $notes = null): void
    {
        MakerChecker::assertApproverDiffers($approver, $this);

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'approved_by_user_id' => $approver->id,
            'notes' => $notes ?? $this->notes,
        ]);

        Audit::record('line_tariff.approved', $this, [
            'status' => self::STATUS_ACTIVE,
            'approved_by' => $approver->id,
        ], 'Persetujuan tarif line');
    }
}
