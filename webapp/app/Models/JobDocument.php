<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobDocument extends Model
{
    use BelongsToCompany, HasFactory;

    public const PHASE_PRA_FILING = 'pra_filing';
    public const PHASE_PASCA_FILING = 'pasca_filing';
    public const PHASE_PASCA_DELIVERY = 'pasca_delivery';

    public const STATUS_BELUM_ADA = 'belum_ada';
    public const STATUS_DIUPLOAD = 'diupload';
    public const STATUS_DIVERIFIKASI = 'diverifikasi';
    public const STATUS_TIDAK_BERLAKU = 'tidak_berlaku';

    // Jenis dokumen standar FCL (§2.4, Q21)
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_PACKING_LIST = 'packing_list';
    public const TYPE_BL = 'bl';
    public const TYPE_FORM_FTA = 'form_fta';
    public const TYPE_DO = 'do';
    public const TYPE_SPPB = 'sppb';
    public const TYPE_GATE_OUT_PROOF = 'gate_out_proof';
    public const TYPE_GATE_IN_PROOF = 'gate_in_proof';
    public const TYPE_POD = 'pod';

    protected $fillable = [
        'job_id',
        'phase',
        'document_type',
        'status',
        'file_path',
        'file_name',
        'metadata',
        'uploaded_by_user_id',
        'uploaded_at',
        'verified_by_user_id',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function markVerified(User $verifier, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_DIVERIFIKASI,
            'verified_by_user_id' => $verifier->id,
            'verified_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        Audit::record('job_document.verified', $this, [
            'status' => self::STATUS_DIVERIFIKASI,
            'verified_by' => $verifier->id,
        ], "Verifikasi dokumen {$this->document_type}");

        // Hitung ulang status job setelah ada dokumen diverifikasi
        $this->job->recomputeStatus();
    }
}
