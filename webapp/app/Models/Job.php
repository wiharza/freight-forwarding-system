<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_DOKUMEN_LENGKAP = 'Dokumen Lengkap';

    public const STATUS_PIB_FILED = 'PIB Filed';

    public const STATUS_SPPB = 'SPPB';

    public const STATUS_GATE_OUT_SEBAGIAN = 'Gate-Out Sebagian';

    public const STATUS_GATE_OUT_SELESAI = 'Gate-Out Selesai';

    public const STATUS_TERKIRIM = 'Terkirim';

    public const STATUS_GATE_IN_SEBAGIAN = 'Gate-In Empty Sebagian';

    public const STATUS_GATE_IN_SELESAI = 'Gate-In Empty Selesai';

    public const STATUS_DITAGIH = 'Ditagih';

    public const STATUS_LUNAS = 'Lunas';

    public const STATUS_CLOSED = 'Closed';

    protected $fillable = [
        'job_number',
        'importir_id',
        'shipping_line_id',
        'bl_number',
        'bl_date',
        'pol_id',
        'pod_id',
        'vessel_id',
        'vessel_name',
        'voyage_number',
        'eta',
        'ata',
        'is_fcl',
        'claim_fta',
        'fta_scheme',
        'problem_flag',
        'cached_status',
        'closed_at',
        'closed_by_user_id',
        'close_reason',
        'referensi_excel_lama',
        'tanggal_cutover',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'bl_date' => 'date',
            'eta' => 'date',
            'ata' => 'date',
            'tanggal_cutover' => 'date',
            'closed_at' => 'datetime',
            'is_fcl' => 'boolean',
            'claim_fta' => 'boolean',
        ];
    }

    public function importir(): BelongsTo
    {
        return $this->belongsTo(Importir::class);
    }

    public function shippingLine(): BelongsTo
    {
        return $this->belongsTo(ShippingLine::class);
    }

    public function pol(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pol_id');
    }

    public function pod(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pod_id');
    }

    /** Kapal dari master (DQ-18) — sumber IMO; `vessel_name` tetap snapshot riwayat. */
    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(JobCost::class);
    }

    /**
     * Biaya pihak ketiga yang boleh ditagih ke Importir (Blok B, §6.4).
     * SATU-SATUNYA definisi billable — dipakai preview (InvoiceController::
     * create) dan submit (store) supaya keduanya tidak bisa menyimpang
     * (HARDENING 2026-11-04; sebelumnya preview dan submit memakai filter
     * berbeda: preview tidak menyaring status aktual, tidak menyaring
     * deposit-forfeit, dan tidak menyaring biaya yang sudah ditagih —
     * karena memang belum ada penandanya).
     *
     *  - payer_flag != importir_langsung: biaya yang dibayar langsung oleh
     *    Importir tidak ditagih. Ini JUGA penanda ocean freight per
     *    spesifikasi §6.4: prepaid (dibayar langsung importir) -> tidak
     *    ditagih, hanya dilacak untuk CIF; collect (dibayar PPJK) -> masuk
     *    tagihan sebagai pass-through. Karena itu TIDAK ada lagi pengecualian
     *    cost_type ocean_freight di sini — pengecualian lama membuat freight
     *    yang dibayar PPJK hilang dari tagihan (kebocoran yang sama
     *    keluarganya dengan Blok B yang dulu hardcoded 0).
     *  - status aktual: estimasi bukan tagihan.
     *  - bukan container_deposit_forfeit: deposit punya ledger sendiri
     *    (ADR 0005), bukan item invoice.
     *  - belum pernah ditagih (invoice_id null).
     */
    public function billableCosts(): HasMany
    {
        return $this->costs()
            ->where('payer_flag', '!=', JobCost::PAYER_IMPORTIR)
            ->where('cost_type', '!=', JobCost::TYPE_DEPOSIT_FORFEIT)
            ->where('status', JobCost::STATUS_AKTUAL)
            ->whereNull('invoice_id');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(ContainerDeposit::class);
    }

    public function uangMukas(): HasMany
    {
        return $this->hasMany(UangMuka::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(JobDocument::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Penutup Job (§2.3, Q11) — siapa yang menutup, untuk banner & pesan kunci.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Apakah Job sedang dalam keadaan tertutup (§2.3, Q11). Closed adalah
     * keadaan di atas status turunan milestone: selama tertutup, status
     * turunan tidak boleh menimpanya (lihat recomputeStatus()).
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'field_job_assignments', 'job_id', 'field_user_id')
            ->withPivot(['company_id', 'assigned_by'])
            ->withTimestamps();
    }

    public function assignField(User|int $fieldUser, ?User $assignedBy = null): void
    {
        $userId = $fieldUser instanceof User ? $fieldUser->id : $fieldUser;
        $this->fields()->syncWithoutDetaching([
            $userId => [
                'company_id' => $this->company_id,
                'assigned_by' => $assignedBy?->id,
            ],
        ]);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasAnyRole([
            Role::ADMIN,
            Role::CS,
            Role::CUSTOMS_SPECIALIST,
            Role::OPS,
            Role::FINANCE,
        ])) {
            return;
        }

        if ($user->hasRole(Role::FIELD)) {
            $query->whereHas('fields', fn (Builder $q) => $q->whereKey($user->id));

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * Inisialisasi checklist dokumen berfase (§2.4, Q21).
     */
    public function initializeChecklist(): void
    {
        // Ambil override jika ada
        $overrides = ImportirChecklistOverride::query()
            ->where('importir_id', $this->importir_id)
            ->pluck('is_required', 'document_type');

        $defaultDocs = [
            // Fase Pra-Filing
            [
                'phase' => JobDocument::PHASE_PRA_FILING,
                'type' => JobDocument::TYPE_INVOICE,
                'required' => $overrides[JobDocument::TYPE_INVOICE] ?? true,
            ],
            [
                'phase' => JobDocument::PHASE_PRA_FILING,
                'type' => JobDocument::TYPE_PACKING_LIST,
                'required' => $overrides[JobDocument::TYPE_PACKING_LIST] ?? true,
            ],
            [
                'phase' => JobDocument::PHASE_PRA_FILING,
                'type' => JobDocument::TYPE_BL,
                'required' => $overrides[JobDocument::TYPE_BL] ?? true,
            ],
            [
                'phase' => JobDocument::PHASE_PRA_FILING,
                'type' => JobDocument::TYPE_FORM_FTA,
                'required' => (bool) $this->claim_fta, // kondisional
            ],

            // Fase Pasca-Filing
            [
                'phase' => JobDocument::PHASE_PASCA_FILING,
                'type' => JobDocument::TYPE_DO,
                'required' => true,
            ],
            [
                'phase' => JobDocument::PHASE_PASCA_FILING,
                'type' => JobDocument::TYPE_SPPB,
                'required' => true,
            ],

            // Fase Pasca-Delivery
            [
                'phase' => JobDocument::PHASE_PASCA_DELIVERY,
                'type' => JobDocument::TYPE_GATE_OUT_PROOF,
                'required' => true,
            ],
            [
                'phase' => JobDocument::PHASE_PASCA_DELIVERY,
                'type' => JobDocument::TYPE_GATE_IN_PROOF,
                'required' => true,
            ],
            [
                'phase' => JobDocument::PHASE_PASCA_DELIVERY,
                'type' => JobDocument::TYPE_POD,
                'required' => true,
            ],
        ];

        foreach ($defaultDocs as $doc) {
            $status = $doc['required'] ? JobDocument::STATUS_BELUM_ADA : JobDocument::STATUS_TIDAK_BERLAKU;

            JobDocument::query()->firstOrCreate(
                ['job_id' => $this->id, 'document_type' => $doc['type']],
                [
                    'company_id' => $this->company_id,
                    'phase' => $doc['phase'],
                    'status' => $status,
                ]
            );
        }
    }

    /**
     * Hitung ulang status progres turunan (§2.3, Q11).
     * Urutan: Closed -> Lunas -> Ditagih -> Gate-In Empty -> Terkirim -> Gate-Out -> SPPB -> PIB Filed -> Dokumen Lengkap -> Draft.
     *
     * Closed mengunci status: Job yang tertutup TIDAK dihitung ulang dari
     * milestone — penutupan hanya dibuka lewat reopen beralasan (§2.3).
     */
    public function recomputeStatus(): string
    {
        if ($this->isClosed()) {
            return $this->setAndReturnStatus(self::STATUS_CLOSED);
        }

        // 1. Cek Gate-In Empty (level kontainer)
        $totalContainers = $this->containers()->count();
        if ($totalContainers > 0) {
            $gateInCount = $this->milestones()
                ->where('is_active', true)
                ->where('milestone_type', Milestone::TYPE_GATE_IN_EMPTY)
                ->distinct('container_id')
                ->count('container_id');

            if ($gateInCount >= $totalContainers) {
                return $this->setAndReturnStatus(self::STATUS_GATE_IN_SELESAI);
            }

            if ($gateInCount > 0) {
                return $this->setAndReturnStatus(self::STATUS_GATE_IN_SEBAGIAN);
            }

            // 2. Cek Terkirim / Delivered
            $deliveredExists = $this->milestones()
                ->where('is_active', true)
                ->where('milestone_type', Milestone::TYPE_DELIVERED)
                ->exists();

            if ($deliveredExists) {
                return $this->setAndReturnStatus(self::STATUS_TERKIRIM);
            }

            // 3. Cek Gate-Out Full
            $gateOutCount = $this->milestones()
                ->where('is_active', true)
                ->where('milestone_type', Milestone::TYPE_GATE_OUT_FULL)
                ->distinct('container_id')
                ->count('container_id');

            if ($gateOutCount >= $totalContainers) {
                return $this->setAndReturnStatus(self::STATUS_GATE_OUT_SELESAI);
            }

            if ($gateOutCount > 0) {
                return $this->setAndReturnStatus(self::STATUS_GATE_OUT_SEBAGIAN);
            }
        }

        // 4. Cek Cabang Pabean
        $hasSppb = $this->milestones()
            ->where('is_active', true)
            ->where('milestone_type', Milestone::TYPE_SPPB)
            ->exists();

        if ($hasSppb) {
            return $this->setAndReturnStatus(self::STATUS_SPPB);
        }

        $hasPib = $this->milestones()
            ->where('is_active', true)
            ->where('milestone_type', Milestone::TYPE_PIB_FILED)
            ->exists();

        if ($hasPib) {
            return $this->setAndReturnStatus(self::STATUS_PIB_FILED);
        }

        // 5. Cek Pra-Filing Checklist
        $praFilingIncomplete = $this->documents()
            ->where('phase', JobDocument::PHASE_PRA_FILING)
            ->where('status', '!=', JobDocument::STATUS_DIVERIFIKASI)
            ->where('status', '!=', JobDocument::STATUS_TIDAK_BERLAKU)
            ->exists();

        $status = $praFilingIncomplete ? self::STATUS_DRAFT : self::STATUS_DOKUMEN_LENGKAP;

        return $this->setAndReturnStatus($status);
    }

    private function setAndReturnStatus(string $status): string
    {
        if ($this->cached_status !== $status) {
            $this->update(['cached_status' => $status]);
        }

        return $status;
    }
}
