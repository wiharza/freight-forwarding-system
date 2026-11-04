<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratJalan extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PRINTED = 'printed';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'trip_id',
        'sj_number',
        'sj_date',
        'driver_name',
        'driver_phone',
        'vehicle_number',
        'origin_address',
        'destination_address',
        'containers_info',
        'status',
        'file_path',
        'pod_file_path',
        'pod_signed_at',
        'created_by_user_id',
        'signed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sj_date' => 'date',
            'containers_info' => 'array',
            'pod_signed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }
}
