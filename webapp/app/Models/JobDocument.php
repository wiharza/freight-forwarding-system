<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Audit;
use App\Support\PrivateFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

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
        // TEMUAN AUDIT 8 ITEM (2026-11-05, item 6): verifikasi mensyaratkan
        // bukti file — baris checklist yang belum pernah diupload (file_path
        // kosong, atau file sudah tidak ada di disk) TIDAK BOLEH menjadi
        // `diverifikasi`, kalau tidak checklist bisa tampil lengkap tanpa
        // bukti apa pun. Ditegakkan di sini (jalur bersama semua pemanggil),
        // bukan hanya di controller.
        if (! PrivateFile::exists($this->file_path)) {
            throw ValidationException::withMessages([
                'document' => "Dokumen {$this->document_type} belum diupload — verifikasi membutuhkan file bukti. Upload filenya terlebih dahulu.",
            ]);
        }

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

    /**
     * Batalkan verifikasi: status kembali ke 'diupload', data verifikator
     * dibersihkan. Alasan wajib (divalidasi di controller) dan tercatat
     * di audit trail — riwayat verifikasi sebelumnya tetap ada di audit,
     * tidak dihapus.
     */
    public function markUnverified(User $user, string $reason): void
    {
        $previousVerifier = $this->verified_by_user_id;

        $this->update([
            'status' => self::STATUS_DIUPLOAD,
            'verified_by_user_id' => null,
            'verified_at' => null,
            'notes' => $reason,
        ]);

        Audit::record('job_document.unverified', $this, [
            'status' => self::STATUS_DIUPLOAD,
            'unverified_by' => $user->id,
            'previous_verifier' => $previousVerifier,
        ], $reason);

        // Hitung ulang status job setelah verifikasi dibatalkan
        $this->job->recomputeStatus();
    }
}
