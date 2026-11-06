<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Milestone;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * Cache ringkasan free time dashboard (keputusan user 2026-11-06:
 * cache + paginasi).
 *
 * Masalah: DashboardController menjalankan DemurrageCalculator untuk
 * SETIAP Job aktif di setiap request. Hitungannya mahal (per kontainer:
 * baca milestone discharge/gate-out/gate-in + tarif line + Kurs Internal),
 * padahal inputnya jarang berubah — hanya saat milestone dicatat /
 * diverifikasi / dikoreksi.
 *
 * Desain: hasil per-Job di-cache 10 menit (backstop TTL) dan DIHAPUS
 * eksplisit setiap kali milestone Job itu berubah (store/verify/supersede
 * di MilestoneController). Cache tidak pernah bertahan melewati perubahan
 * milestone. Kunci mencakup company_id agar tidak bocor antar-tenant.
 *
 * Yang di-cache adalah hasil mentah calculateForJob (array skalar —
 * aman di-serialize ke cache database/file) plus status "tidak terhitung"
 * saat Kurs Internal belum diatur. Statistik (overdue/urgent/ok) tetap
 * dihitung dari seluruh Job aktif — tapi dari nilai cache, bukan dari
 * kalkulator.
 */
class DashboardFreeTime
{
    /** Backstop TTL — pengaman bila ada jalur mutasi yang luput. */
    public const TTL_SECONDS = 600;

    /** Baris Job per halaman di tabel dashboard. */
    public const PER_PAGE = 15;

    public static function key(int $companyId, int $jobId): string
    {
        return "dashboard:free_time:{$companyId}:{$jobId}";
    }

    public static function forgetForJob(Job $job): void
    {
        Cache::forget(self::key((int) $job->company_id, (int) $job->id));
    }

    /**
     * @return array{calc: array|null, unavailable: bool, may_have_clock: bool}
     */
    public function forJob(Job $job, DemurrageCalculator $calculator): array
    {
        return Cache::remember(
            self::key((int) $job->company_id, (int) $job->id),
            self::TTL_SECONDS,
            function () use ($job, $calculator) {
                try {
                    return [
                        'calc' => $calculator->calculateForJob($job),
                        'unavailable' => false,
                        'may_have_clock' => false,
                    ];
                } catch (LogicException) {
                    // Kurs Internal belum diatur — kalkulator gagal.
                    // Simpan juga hasil "tidak terhitung" agar request
                    // berikutnya tidak mengulang kalkulasi yang pasti gagal
                    // (sampai TTL habis atau milestone berubah).
                    return [
                        'calc' => null,
                        'unavailable' => true,
                        'may_have_clock' => $this->jobMayHaveRunningClock($job),
                    ];
                }
            }
        );
    }

    /**
     * Apakah Job ini mungkin punya jam demurrage/detention yang berjalan —
     * dipakai saat kalkulator gagal (kurs belum diatur) untuk membedakan
     * "tidak ada jam berjalan" dari "hitungan tidak tersedia".
     */
    private function jobMayHaveRunningClock(Job $job): bool
    {
        return $job->milestones()
            ->where('is_active', true)
            ->whereIn('milestone_type', [Milestone::TYPE_DISCHARGE, Milestone::TYPE_GATE_OUT_FULL])
            ->exists();
    }
}
