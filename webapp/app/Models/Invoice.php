<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Audit;
use App\Support\MakerChecker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Invoice extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'job_id',
        'importir_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'blok_a_bm',
        'blok_a_ppn_impor',
        'blok_a_pph22',
        'blok_a_ppnbm',
        'blok_a_pungutan_lain',
        'blok_a_total',
        'snapshot_pib_id',
        'blok_b_total',
        'blok_b_ppn',
        'blok_c_service_fee',
        'blok_c_dpp_lain',
        'blok_c_ppn_rate',
        'blok_c_ppn',
        'total_invoice',
        'total_paid',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'faktur_pajak_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'approved_at' => 'datetime',
            'blok_a_bm' => 'decimal:4',
            'blok_a_ppn_impor' => 'decimal:4',
            'blok_a_pph22' => 'decimal:4',
            'blok_a_ppnbm' => 'decimal:4',
            'blok_a_pungutan_lain' => 'decimal:4',
            'blok_a_total' => 'decimal:4',
            'blok_b_total' => 'decimal:4',
            'blok_b_ppn' => 'decimal:4',
            'blok_c_service_fee' => 'decimal:4',
            'blok_c_dpp_lain' => 'decimal:4',
            'blok_c_ppn_rate' => 'decimal:4',
            'blok_c_ppn' => 'decimal:4',
            'total_invoice' => 'decimal:4',
            'total_paid' => 'decimal:4',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function importir(): BelongsTo
    {
        return $this->belongsTo(Importir::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('blok');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Batas approve berlaku di luar blok pungutan negara (ADR 0007):
     * blok b (biaya pihak ketiga) + blok c (jasa PPJK + PPN atas jasa).
     */
    public function approvableAmount(): float
    {
        return (float) ($this->blok_b_total + $this->blok_c_service_fee + $this->blok_c_ppn);
    }

    /**
     * Approve invoice dengan Maker-Checker + Batas Approve (Q33, ADR 0007).
     */
    public function approve(User $approver, ?string $fakturPajakNumber = null): void
    {
        // Maker-checker: pembuat tidak boleh menyetujui sendiri
        MakerChecker::assertApproverDiffers($approver, $this);

        // Cek batas approve per user Finance
        $limit = FinanceApprovalLimit::query()->where('user_id', $approver->id)->first();

        if ($limit && ! $limit->unlimited) {
            if ($this->approvableAmount() > (float) $limit->limit_amount) {
                throw ValidationException::withMessages([
                    'approval_limit' => "Total invoice (di luar pungutan negara) Rp {$this->approvableAmount()} melebihi batas approve Anda (Rp {$limit->limit_amount}).",
                ]);
            }
        }

        $this->update([
            'status' => self::STATUS_LOCKED,
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
            'faktur_pajak_number' => $fakturPajakNumber ?? $this->faktur_pajak_number,
        ]);

        Audit::record('invoice.approved', $this, [
            'status' => self::STATUS_LOCKED,
            'approved_by' => $approver->id,
            'approvable_amount' => $this->approvableAmount(),
        ], 'Persetujuan invoice');
    }

    /**
     * Total sisa tagihan.
     */
    public function sisaTagihan(): float
    {
        return (float) $this->total_invoice - (float) $this->total_paid;
    }
}