<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRoute extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'vendor_id',
        'origin_location_id',
        'destination_location_id',
        'container_size',
        'base_price_usd',
        'include_toll',
        'include_unloading',
        'include_waiting',
        'waiting_rate_per_hour_usd',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'base_price_usd' => 'decimal:4',
            'waiting_rate_per_hour_usd' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'include_toll' => 'boolean',
            'include_unloading' => 'boolean',
            'include_waiting' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}
