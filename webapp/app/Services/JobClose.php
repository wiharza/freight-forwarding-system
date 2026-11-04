<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\Milestone;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penutupan & pembukaan kembali Job (§2.3, Q11).
 *
 * Syarat close (semua harus lolos, kalau tidak close ditolak dengan daftar
 * blocker yang menyebut nama + angka):
 *  (a) tagihan lunas — ada invoice (bukan dibatalkan) dan semuanya lunas;
 *  (b) semua kontainer gate-in empty;
 *  (c) arsip dokumen seluruh fase lengkap (semua yang berlaku terverifikasi);
 *  (d) tidak ada selisih demurrage/detention yang belum diselesaikan —
 *      di v1 selisih yang diperselisihkan direpresentasikan Problem Flag
 *      "Dispute" (§6.3.1: dem/det yang diperselisihkan tetap ditagih
 *      pass-through; bila PPJK menanggung sebagian → Dispute + nota kredit
 *      manual, bukan logika sistem);
 *  (e) settlement uang muka per Job sama dengan nol (§6.3.1).
 *
 * Closed adalah keadaan di atas status turunan milestone (ADR 0002): status
 * tidak pernah diedit manual, dan recomputeStatus() tidak menimpa Closed.
 * Riwayat penutupan/pembukaan ulang tersimpan di audit trail (append-only),
 * bukan dihapus saat reopen.
 */
class JobClose
{
    public const CHECK_INVOICE_LUNAS = 'invoice_lunas';

    public const CHECK_GATE_IN_EMPTY = 'gate_in_empty';

    public const CHECK_ARSIP_LENGKAP = 'arsip_lengkap';

    public const CHECK_TANPA_SELISIH_DEMDET = 'tanpa_selisih_demdet';

    public const CHECK_SETTLEMENT_NOL = 'settlement_nol';

    /**
     * @return array<int, array{key: string, label: string, passed: bool, detail: string|null}>
     */
    public function preconditions(Job $job): array
    {
        return [
            $this->checkInvoiceLunas($job),
            $this->checkGateInEmpty($job),
            $this->checkArsipLengkap($job),
            $this->checkTanpaSelisihDemdet($job),
            $this->checkSettlementNol($job),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, passed: bool, detail: string|null}>
     */
    public function blockers(Job $job): array
    {
        return array_values(array_filter(
            $this->preconditions($job),
            fn (array $check) => ! $check['passed'],
        ));
    }

    public function assertClosable(Job $job): void
    {
        $blockers = $this->blockers($job);

        if ($blockers === []) {
            return;
        }

        $lines = array_map(
            fn (array $b) => $b['label'].($b['detail'] ? ': '.$b['detail'] : ''),
            $blockers,
        );

        throw ValidationException::withMessages([
            'close' => 'Job belum bisa ditutup: '.implode(' | ', $lines),
            'blockers' => $lines,
        ]);
    }

    public function close(Job $job, User $user, string $reason): void
    {
        if ($job->isClosed()) {
            throw ValidationException::withMessages([
                'close' => 'Job ini sudah ditutup.',
            ]);
        }

        $this->assertClosable($job);

        DB::transaction(function () use ($job, $user, $reason) {
            $job->update([
                'closed_at' => now(),
                'closed_by_user_id' => $user->id,
                'close_reason' => $reason,
                'cached_status' => Job::STATUS_CLOSED,
            ]);

            Audit::record('job.closed', $job, [
                'closed_by' => $user->id,
                'closed_at' => $job->closed_at->toDateTimeString(),
            ], $reason);
        });
    }

    public function reopen(Job $job, User $user, string $reason): void
    {
        if (! $job->isClosed()) {
            throw ValidationException::withMessages([
                'reopen' => 'Job ini tidak dalam keadaan tertutup.',
            ]);
        }

        // Maker-checker (§2.5, ADR 0006): reopen close masuk daftar aksi yang
        // wajib maker-checker — penutup tidak boleh membuka kembali
        // tutupannya sendiri, walau memegang beberapa peran sekaligus.
        if ($job->closed_by_user_id !== null && (int) $job->closed_by_user_id === (int) $user->id) {
            throw ValidationException::withMessages([
                'maker_checker' => 'Penutup Job tidak boleh membuka kembali tutupannya sendiri (Maker-Checker, ADR 0006).',
            ]);
        }

        DB::transaction(function () use ($job, $user, $reason) {
            $closedBy = $job->closed_by_user_id;
            $closedAt = $job->closed_at;
            $closeReason = $job->close_reason;

            $job->update([
                'closed_at' => null,
                'closed_by_user_id' => null,
                'close_reason' => null,
            ]);

            // Status kembali ke proyeksi milestone (§2.3). recomputeStatus()
            // menghormati keadaan tertutup, jadi dipanggil setelah field
            // penutupan dibersihkan.
            $job->recomputeStatus();

            Audit::record('job.reopened', $job, [
                'closed_by' => $closedBy,
                'closed_at' => $closedAt?->toDateTimeString(),
                'close_reason' => $closeReason,
                'reopened_by' => $user->id,
            ], $reason);
        });
    }

    /**
     * Kunci tulis untuk Job tertutup (§2.3): milestone, biaya, invoice, dan
     * dokumen tidak boleh lagi ditulis. Pesan menyebut siapa yang menutup
     * dan kapan, supaya penolakan bisa ditindaklanjuti (reopen), bukan
     * sekadar error.
     */
    public static function assertNotClosed(Job $job): void
    {
        if (! $job->isClosed()) {
            return;
        }

        $job->loadMissing('closedBy:id,name');
        $by = $job->closedBy?->name ?? 'pengguna tidak diketahui';
        $when = $job->closed_at?->format('d-m-Y H:i') ?? '-';

        throw ValidationException::withMessages([
            'job_closed' => "Job ini sudah ditutup oleh {$by} pada {$when} dan terkunci untuk penulisan. Buka kembali Job terlebih dahulu (Admin/Finance, dengan alasan).",
        ]);
    }

    // ------------------------------------------------------------------
    // Pemeriksaan syarat close (§2.3)
    // ------------------------------------------------------------------

    private function checkInvoiceLunas(Job $job): array
    {
        $label = 'Tagihan lunas';

        $invoices = $job->invoices()
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->get(['id', 'invoice_number', 'total_invoice', 'total_paid']);

        if ($invoices->isEmpty()) {
            return ['key' => self::CHECK_INVOICE_LUNAS, 'label' => $label, 'passed' => false, 'detail' => 'Belum ada invoice untuk Job ini.'];
        }

        $sisa = [];
        foreach ($invoices as $invoice) {
            // Uang = desimal (ADR §8.1): bandingkan sebagai string, bukan float.
            if (bccomp((string) $invoice->total_invoice, (string) $invoice->total_paid, 4) > 0) {
                $sisa[] = $invoice->invoice_number.' (sisa Rp '.number_format((float) bcsub((string) $invoice->total_invoice, (string) $invoice->total_paid, 4), 0, ',', '.').')';
            }
        }

        if ($sisa !== []) {
            return ['key' => self::CHECK_INVOICE_LUNAS, 'label' => $label, 'passed' => false, 'detail' => 'Belum lunas: '.implode(', ', $sisa).'.'];
        }

        return ['key' => self::CHECK_INVOICE_LUNAS, 'label' => $label, 'passed' => true, 'detail' => null];
    }

    private function checkGateInEmpty(Job $job): array
    {
        $label = 'Semua kontainer gate-in empty';

        $containers = $job->containers()->get(['id', 'container_number']);

        if ($containers->isEmpty()) {
            return ['key' => self::CHECK_GATE_IN_EMPTY, 'label' => $label, 'passed' => false, 'detail' => 'Job tidak memiliki kontainer.'];
        }

        $gateInIds = $job->milestones()
            ->where('is_active', true)
            ->where('milestone_type', Milestone::TYPE_GATE_IN_EMPTY)
            ->distinct('container_id')
            ->pluck('container_id')
            ->all();

        $missing = $containers
            ->reject(fn ($c) => in_array($c->id, $gateInIds, true))
            ->pluck('container_number')
            ->all();

        if ($missing !== []) {
            return ['key' => self::CHECK_GATE_IN_EMPTY, 'label' => $label, 'passed' => false, 'detail' => 'Belum gate-in empty: '.implode(', ', $missing).'.'];
        }

        return ['key' => self::CHECK_GATE_IN_EMPTY, 'label' => $label, 'passed' => true, 'detail' => null];
    }

    private function checkArsipLengkap(Job $job): array
    {
        $label = 'Arsip dokumen seluruh fase lengkap';

        $incomplete = $job->documents()
            ->where('status', '!=', JobDocument::STATUS_TIDAK_BERLAKU)
            ->where('status', '!=', JobDocument::STATUS_DIVERIFIKASI)
            ->get(['phase', 'document_type', 'status']);

        if ($incomplete->isNotEmpty()) {
            $names = $incomplete->map(
                fn ($d) => str_replace('_', ' ', $d->document_type)." ({$d->phase}: {$d->status})",
            )->all();

            return ['key' => self::CHECK_ARSIP_LENGKAP, 'label' => $label, 'passed' => false, 'detail' => 'Belum terverifikasi: '.implode(', ', $names).'.'];
        }

        return ['key' => self::CHECK_ARSIP_LENGKAP, 'label' => $label, 'passed' => true, 'detail' => null];
    }

    private function checkTanpaSelisihDemdet(Job $job): array
    {
        $label = 'Tidak ada selisih demurrage/detention yang belum diselesaikan';

        if ($job->problem_flag === 'Dispute') {
            return [
                'key' => self::CHECK_TANPA_SELISIH_DEMDET,
                'label' => $label,
                'passed' => false,
                'detail' => 'Problem Flag "Dispute" masih aktif — selisih demurrage/detention belum diselesaikan (§6.3.1).',
            ];
        }

        return ['key' => self::CHECK_TANPA_SELISIH_DEMDET, 'label' => $label, 'passed' => true, 'detail' => null];
    }

    private function checkSettlementNol(Job $job): array
    {
        $label = 'Settlement uang muka sama dengan nol';

        $remaining = '0';
        foreach ($job->uangMukas()->get(['amount', 'settled_amount']) as $row) {
            $remaining = bcadd(
                $remaining,
                bcsub((string) $row->amount, (string) ($row->settled_amount ?? '0'), 4),
                4,
            );
        }

        if (bccomp($remaining, '0', 4) !== 0) {
            return [
                'key' => self::CHECK_SETTLEMENT_NOL,
                'label' => $label,
                'passed' => false,
                'detail' => 'Sisa settlement uang muka Rp '.number_format((float) $remaining, 0, ',', '.').' belum nol.',
            ];
        }

        return ['key' => self::CHECK_SETTLEMENT_NOL, 'label' => $label, 'passed' => true, 'detail' => null];
    }
}
