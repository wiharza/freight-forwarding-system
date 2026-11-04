<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCost extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPE_OCEAN_FREIGHT = 'ocean_freight';

    public const TYPE_THC = 'thc';

    public const TYPE_DEMURRAGE = 'demurrage';

    public const TYPE_DETENTION = 'detention';

    public const TYPE_DO_FEE = 'do_fee';

    public const TYPE_ADMIN_TERMINAL = 'admin_terminal';

    public const TYPE_KARANTINA = 'karantina';

    public const TYPE_TRUCKING = 'trucking';

    public const TYPE_DEPOSIT_FORFEIT = 'container_deposit_forfeit';

    public const PAYER_PPJK = 'ppjk';

    public const PAYER_IMPORTIR = 'importir_langsung';

    public const STATUS_ESTIMASI = 'estimasi';

    public const STATUS_AKTUAL = 'aktual';

    protected $fillable = [
        'job_id',
        'cost_type',
        'payer_flag',
        'currency_code',
        'amount_valas',
        'kurs_internal_rate',
        'amount_idr',
        'status',
        'bukti_path',
        'bukti_number',
        'notes',
        'created_by_user_id',
        // HARDENING 2026-11-04: penanda sudah ditagih (lihat migrasi
        // 2026_11_04_001300). Diisi saat invoice dibuat; dipakai
        // Job::billableCosts() untuk mencegah double-billing.
        'invoice_id',
        'invoiced_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_valas' => 'decimal:4',
            'kurs_internal_rate' => 'decimal:4',
            'amount_idr' => 'decimal:4',
            'invoiced_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isBillable(): bool
    {
        return $this->payer_flag !== self::PAYER_IMPORTIR;
    }
}
