<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

class InternalExchangeRate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'rate_date',
        'currency_code',
        'rate_to_idr',
        'cutoff_time',
        'is_locked',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate_to_idr' => 'decimal:4',
            'is_locked' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (InternalExchangeRate $rate) {
            if ($rate->getOriginal('is_locked')) {
                throw new LogicException('Kurs internal yang sudah dipakai invoice terkunci dan tidak dapat diedit (ADR 0003, §6.6).');
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Dapatkan kurs internal untuk mata uang & tanggal tertentu.
     * Mengembalikan array ['rate' => string, 'is_official_today' => bool, 'model' => ?InternalExchangeRate].
     */
    public static function resolveRate(string $currencyCode, ?CarbonInterface $date = null): array
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        // 1. Cek kurs resmi persis tanggal tersebut
        $exactRate = self::query()
            ->where('currency_code', strtoupper($currencyCode))
            ->whereDate('rate_date', $date)
            ->first();

        if ($exactRate) {
            return [
                'rate' => (string) $exactRate->rate_to_idr,
                'is_official_today' => true,
                'rate_date' => $exactRate->rate_date->toDateString(),
                'model' => $exactRate,
            ];
        }

        // 2. Fallback ke kurs terakhir sebelumnya (draf boleh memakai dengan flag)
        $latestRate = self::query()
            ->where('currency_code', strtoupper($currencyCode))
            ->whereDate('rate_date', '<', $date)
            ->orderByDesc('rate_date')
            ->first();

        if ($latestRate) {
            return [
                'rate' => (string) $latestRate->rate_to_idr,
                'is_official_today' => false,
                'rate_date' => $latestRate->rate_date->toDateString(),
                'model' => $latestRate,
            ];
        }

        // TEMUAN #4 (2026-11-04): tanpa baris kurs sama sekali, jangan
        // diam-diam mengembalikan 1.0 — pemanggil (DemurrageCalculator)
        // akan menghitung biaya IDR yang salah total tanpa jejak. IDR = 1.0
        // menurut definisi dan tidak butuh baris kurs; valas tanpa baris
        // kurs sama sekali = gagal dengan pesan yang jelas. (Fallback ke
        // kurs terakhir sebelumnya di atas tetap berlaku — ini hanya
        // terpicu bila TIDAK ADA baris kurs untuk mata uang itu.)
        if (strtoupper($currencyCode) === 'IDR') {
            return [
                'rate' => '1.0000',
                'is_official_today' => true,
                'rate_date' => $date,
                'model' => null,
            ];
        }

        throw new LogicException(
            "Kurs Internal {$currencyCode} belum diatur untuk tanggal {$date} (tidak ada baris kurs sama sekali) — isi Kurs Internal terlebih dahulu."
        );
    }
}
