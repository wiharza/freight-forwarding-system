<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        // Tautan alarm Free Time (§2.6, Q8, Tiket 15) — diisi oleh
        // NotificationService::notifyFreeTimeAlert, bukan oleh pengirim umum.
        'user_id',
        'job_id',
        'trigger',
        'charge_type',
        'free_time_date',
    ];

    protected function casts(): array
    {
        return [
            'sent_successfully' => 'boolean',
            'sent_at' => 'datetime',
            'free_time_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    /**
     * Entri kotak masuk (badge) milik satu pengguna — hanya baris badge
     * yang punya user_id; baris email lama tanpa user_id bukan inbox.
     */
    public function scopeInboxFor(Builder $query, User $user): Builder
    {
        return $query->where('channel', self::CHANNEL_BADGE)
            ->where('user_id', $user->id);
    }

    /** Entri inbox yang belum dibaca pengguna tersebut. */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->inboxFor($user)
            ->whereDoesntHave('reads', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public static function unreadCountFor(User $user): int
    {
        return self::query()->unreadFor($user)->count();
    }

    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    public function markAsReadBy(User $user): void
    {
        NotificationRead::query()->firstOrCreate(
            ['user_id' => $user->id, 'notification_log_id' => $this->id],
            ['read_at' => now()]
        );
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Log notifikasi bersifat append-only.');
        });

        static::deleting(function () {
            throw new LogicException('Log notifikasi tidak dapat dihapus.');
        });
    }
}
