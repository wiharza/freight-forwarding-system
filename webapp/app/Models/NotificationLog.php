<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class NotificationLog extends Model
{
    use BelongsToCompany, HasFactory;

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_TELEGRAM = 'telegram';
    public const CHANNEL_BADGE = 'badge';

    protected $fillable = [
        'channel',
        'recipient',
        'recipient_name',
        'subject',
        'body',
        'related_type',
        'related_id',
        'container_number',
        'sent_successfully',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_successfully' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Log notifikasi bersifat append-only.');
        });

        static::deleting(function () {
            throw new \LogicException('Log notifikasi tidak dapat dihapus.');
        });
    }
}