<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FtaDecision extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPE_MFN_NOW = 'mfn_now';

    public const TYPE_DELAY = 'delay';

    public const TYPE_WARNING_8DIGIT = 'warning_8digit';

    public const TYPES = [
        self::TYPE_MFN_NOW,
        self::TYPE_DELAY,
        self::TYPE_WARNING_8DIGIT,
    ];

    protected $fillable = [
        'job_id',
        'customs_declaration_id',
        'customs_item_id',
        'fta_form_id',
        'decision_type',
        'reason',
        'risk_note',
        'refund_reference',
        'created_by_user_id',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(CustomsDeclaration::class, 'customs_declaration_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CustomsItem::class, 'customs_item_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FtaForm::class, 'fta_form_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
