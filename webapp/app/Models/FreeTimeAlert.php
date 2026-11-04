<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreeTimeAlert extends Model
{
    use BelongsToCompany, HasFactory;

    public const CHARGE_DEMURRAGE = 'demurrage';

    public const CHARGE_DETENTION = 'detention';

    public const TRIGGER_H_MINUS_2 = 'h_minus_2';

    public const TRIGGER_H_0 = 'h_0';

    public const TRIGGER_H_PLUS_1 = 'h_plus_1';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** Sisa hari (last free date − hari ini) yang memicu tiap pemicu. */
    public const TRIGGER_REMAINING_DAYS = [
        self::TRIGGER_H_MINUS_2 => 2,
        self::TRIGGER_H_0 => 0,
        self::TRIGGER_H_PLUS_1 => -1,
    ];

    public const TRIGGER_LABELS = [
        self::TRIGGER_H_MINUS_2 => 'H-2',
        self::TRIGGER_H_0 => 'H-0',
        self::TRIGGER_H_PLUS_1 => 'H+1',
    ];

    public const CHARGE_LABELS = [
        self::CHARGE_DEMURRAGE => 'Demurrage',
        self::CHARGE_DETENTION => 'Detention',
    ];

    protected $fillable = [
        'job_id',
        'container_id',
        'charge_type',
        'trigger',
        'free_time_date',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'free_time_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
