<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VendorInvoice extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'vendor_id',
        'invoice_number',
        'invoice_date',
        'currency_code',
        'amount_usd',
        'rate_to_idr',
        'amount_idr',
        'include_toll',
        'include_unloading',
        'waiting_hours',
        'waiting_charge_usd',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'amount_usd' => 'decimal:4',
            'rate_to_idr' => 'decimal:4',
            'amount_idr' => 'decimal:4',
            'waiting_hours' => 'decimal:4',
            'waiting_charge_usd' => 'decimal:4',
            'include_toll' => 'boolean',
            'include_unloading' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_vendor_invoice', 'vendor_invoice_id', 'trip_id')
            ->withTimestamps();
    }
}
