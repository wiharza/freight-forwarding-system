<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Audit;
use App\Support\MakerChecker;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Milestone extends Model
{
    use BelongsToCompany, HasFactory;

    public const BRANCH_PABEAN = 'pabean';

    public const BRANCH_LOGISTIK = 'logistik';

    public const TYPE_DISCHARGE = 'discharge';

    public const TYPE_DO_RELEASE = 'do_release';

    public const TYPE_PIB_FILED = 'pib_filed';

    public const TYPE_SPPB = 'sppb';

    public const TYPE_GATE_OUT_FULL = 'gate_out_full';

    public const TYPE_DELIVERED = 'delivered';

    public const TYPE_GATE_IN_EMPTY = 'gate_in_empty';

    protected $fillable = [
        'job_id',
        'container_id',
        'branch',
        'milestone_type',
        'occurred_at',
        'recorded_at',
        'is_backdate',
        'backdate_reason',
        'proof_number',
        'proof_file_path',
        'notes',
        'created_by_user_id',
        'verified_by_user_id',
        'verified_at',
        'supersedes_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
            'verified_at' => 'datetime',
            'is_backdate' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * Validasi kronologis cabang (§2.2, Q15). Sistem MENOLAK pelanggaran.
     * BUGFIX 2026-11-04: sebelumnya cek hanya membandingkan tanggal BILA
     * pendahulu sudah ada — SPPB tanpa PIB, DO tanpa Discharge, dan
     * Gate-Out tanpa DO semuanya lolos diam-diam. Sekarang pendahulu yang
     * belum ada JUGA ditolak: milestone tidak boleh dicatat sebelum
     * pendahulunya ada di cabang yang sama.
     */
    public static function assertValidChronology(Job $job, string $type, Carbon $occurredAt, ?int $containerId = null): void
    {
        $active = fn (string $t) => $job->milestones()
            ->where('is_active', true)
            ->where('milestone_type', $t)
            ->first();

        // 1. Cabang Pabean: SPPB wajib didahului PIB filed
        if ($type === self::TYPE_SPPB) {
            $pib = $active(self::TYPE_PIB_FILED);

            if (! $pib) {
                throw ValidationException::withMessages([
                    'milestone_type' => 'Validasi kronologis cabang pabean: SPPB tidak dapat dicatat sebelum PIB Filed ada di Job ini.',
                ]);
            }

            if ($occurredAt->lt($pib->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => "Validasi kronologis cabang pabean: Tanggal SPPB ({$occurredAt->format('Y-m-d H:i')}) tidak boleh mendahului tanggal PIB Filed ({$pib->occurred_at->format('Y-m-d H:i')}).",
                ]);
            }
        }

        // 2. Cabang Logistik:
        // DO wajib didahului Discharge
        if ($type === self::TYPE_DO_RELEASE) {
            $discharge = $active(self::TYPE_DISCHARGE);

            if (! $discharge) {
                throw ValidationException::withMessages([
                    'milestone_type' => 'Validasi kronologis: DO Terbit tidak dapat dicatat sebelum Discharge/Bongkar ada di Job ini.',
                ]);
            }

            if ($occurredAt->lt($discharge->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => "Validasi kronologis: Tanggal DO Terbit ({$occurredAt->format('Y-m-d H:i')}) tidak boleh mendahului tanggal Discharge/Bongkar ({$discharge->occurred_at->format('Y-m-d H:i')}).",
                ]);
            }
        }

        // Gate-Out Full wajib didahului DO terbit dan Discharge
        if ($type === self::TYPE_GATE_OUT_FULL) {
            $do = $active(self::TYPE_DO_RELEASE);

            if (! $do) {
                throw ValidationException::withMessages([
                    'milestone_type' => 'Validasi kronologis: Gate-Out Full tidak dapat dicatat sebelum DO Terbit ada di Job ini.',
                ]);
            }

            if ($occurredAt->lt($do->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => "Validasi kronologis: Gate-Out Full ({$occurredAt->format('Y-m-d H:i')}) tidak boleh sebelum DO Terbit ({$do->occurred_at->format('Y-m-d H:i')}).",
                ]);
            }

            $discharge = $active(self::TYPE_DISCHARGE);

            if (! $discharge) {
                throw ValidationException::withMessages([
                    'milestone_type' => 'Validasi kronologis: Gate-Out Full tidak dapat dicatat sebelum Discharge/Bongkar ada di Job ini.',
                ]);
            }

            if ($occurredAt->lt($discharge->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => "Validasi kronologis: Gate-Out Full ({$occurredAt->format('Y-m-d H:i')}) tidak boleh mendahului tanggal Discharge ({$discharge->occurred_at->format('Y-m-d H:i')}).",
                ]);
            }
        }

        // Delivered (Terkirim) wajib didahului Gate-Out Full
        if ($type === self::TYPE_DELIVERED) {
            $gateOut = $containerId
                ? $job->milestones()->where('is_active', true)->where('container_id', $containerId)->where('milestone_type', self::TYPE_GATE_OUT_FULL)->first()
                : $active(self::TYPE_GATE_OUT_FULL);

            if (! $gateOut) {
                throw ValidationException::withMessages([
                    'milestone_type' => 'Validasi kronologis: Delivered tidak dapat dicatat sebelum Gate-Out Full ada di Job ini.',
                ]);
            }

            if ($occurredAt->lt($gateOut->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => "Validasi kronologis: Delivered ({$occurredAt->format('Y-m-d H:i')}) tidak boleh mendahului Gate-Out Full ({$gateOut->occurred_at->format('Y-m-d H:i')}).",
                ]);
            }
        }

        // Gate-In Empty wajib didahului Gate-Out Full pada kontainer yang sama
        if ($type === self::TYPE_GATE_IN_EMPTY) {
            $gateOut = $containerId
                ? $job->milestones()->where('is_active', true)->where('container_id', $containerId)->where('milestone_type', self::TYPE_GATE_OUT_FULL)->first()
                : $active(self::TYPE_GATE_OUT_FULL);

            if (! $gateOut) {
                throw ValidationException::withMessages([
                    'milestone_type' => 'Validasi kronologis: Gate-In Empty tidak dapat dicatat sebelum Gate-Out Full ada di Job ini.',
                ]);
            }

            if ($occurredAt->lt($gateOut->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => "Validasi kronologis: Gate-In Empty ({$occurredAt->format('Y-m-d H:i')}) tidak boleh mendahului tanggal Gate-Out Full ({$gateOut->occurred_at->format('Y-m-d H:i')}) pada kontainer yang sama.",
                ]);
            }
        }
    }

    /**
     * Verifikasi milestone oleh Ops/Admin (§2.7).
     * Menegakkan Maker-Checker: Ops dilarang memverifikasi inputannya sendiri!
     */
    public function verify(User $verifier, ?string $notes = null): void
    {
        MakerChecker::assertApproverDiffers($verifier, $this);

        $this->update([
            'verified_by_user_id' => $verifier->id,
            'verified_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        Audit::record('milestone.verified', $this, [
            'type' => $this->milestone_type,
            'verified_by' => $verifier->id,
        ], "Verifikasi milestone {$this->milestone_type}");

        $this->job->recomputeStatus();
    }

    /**
     * Koreksi pengganti (§2.7, v2.1): milestone lama tidak dihapus,
     * milestone baru menggantikan milestone lama dan menunjuk ke supersedes_id.
     */
    public function supersedeWith(array $newData, User $actor, string $reason): self
    {
        $this->update(['is_active' => false]);

        $replacement = self::query()->create(array_merge($newData, [
            'company_id' => $this->company_id,
            'job_id' => $this->job_id,
            'container_id' => $this->container_id,
            'branch' => $this->branch,
            'milestone_type' => $this->milestone_type,
            'supersedes_id' => $this->id,
            'is_active' => true,
            'created_by_user_id' => $actor->id,
            'recorded_at' => now(),
        ]));

        Audit::record('milestone.superseded', $replacement, [
            'superseded_milestone_id' => $this->id,
            'old_occurred_at' => $this->occurred_at->toDateTimeString(),
            'new_occurred_at' => $replacement->occurred_at->toDateTimeString(),
            'reason' => $reason,
        ], $reason);

        $this->job->recomputeStatus();

        return $replacement;
    }
}
