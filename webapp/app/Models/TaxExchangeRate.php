<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxExchangeRate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'kmk_number',
        'valid_from',
        'valid_to',
        'currency_code',
        'rate_to_idr',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'rate_to_idr' => 'decimal:4',
        ];
    }

    /**
     * Cari Kurs Pajak Kemenkeu yang berlaku pada tanggal tertentu (periode Rabu–Selasa §3.5, PV-3).
     */
    public static function resolveRate(string $currencyCode, ?CarbonInterface $date = null): ?self
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        return self::query()
            ->where('currency_code', strtoupper($currencyCode))
            ->whereDate('valid_from', '<=', $targetDate)
            ->whereDate('valid_to', '>=', $targetDate)
            // Periode KMK riil tidak tumpang tindih; bila dua baris cocok
            // (mis. baris seed lebar di test), yang terbaru yang menang —
            // supaya hasil resolve deterministik.
            ->orderByDesc('valid_from')
            ->first();
    }
}
