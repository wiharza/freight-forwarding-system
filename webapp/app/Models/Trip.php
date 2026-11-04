<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Transisi status yang diizinkan (temuan #6, 2026-11-04): rantai
     * assigned → confirmed → in_transit → delivered; cancelled hanya dari
     * assigned/confirmed. Status delivered/cancelled bersifat final —
     * tidak ada jalan kembali maupun lompatan tahapan.
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_ASSIGNED => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_IN_TRANSIT, self::STATUS_CANCELLED],
        self::STATUS_IN_TRANSIT => [self::STATUS_DELIVERED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
    ];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    protected $fillable = [
        'job_id',
        'vendor_id',
        'trip_number',
        'scheduled_date',
        'actual_departure',
        'actual_arrival',
        'status',
        'driver_id',
        'driver_name',
        'driver_phone',
        'vehicle_number',
        'origin_location_id',
        'destination_location_id',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'actual_departure' => 'datetime',
            'actual_arrival' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function containers(): BelongsToMany
    {
        return $this->belongsToMany(Container::class, 'trip_containers', 'trip_id', 'container_id')
            ->withPivot('sequence')
            ->withTimestamps();
    }

    public function suratJalan(): HasMany
    {
        return $this->hasMany(SuratJalan::class);
    }

    // BUGFIX 2026-11-04: nama relasi jamak — TripController::show() dan
    // halaman trips/show.tsx memakai 'vendorInvoices' (diserialisasi menjadi
    // vendor_invoices); nama singular lama melempar RelationNotFoundException.
    public function vendorInvoices(): BelongsToMany
    {
        return $this->belongsToMany(VendorInvoice::class, 'trip_vendor_invoice', 'trip_id', 'vendor_invoice_id')
            ->withTimestamps();
    }
}
