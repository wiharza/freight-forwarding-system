<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\NotificationLog;
use App\Services\DemurrageCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * Dashboard (§2.1 / laporan (a) Q27): ringkasan operasional sekali lihat —
 * Job aktif per status, Problem Flag, hitung mundur free time, tunggakan
 * invoice, notifikasi belum dibaca, dan aktivitas terakhir.
 *
 * Sebelumnya halaman ini hanya template bawaan Laravel (kotak placeholder
 * tanpa data). Angka dihitung dari helper yang sudah ada (DemurrageCalculator,
 * NotificationLog::unreadCountFor) — tidak ada perhitungan baru yang
 * menduplikasi logika kalkulator.
 *
 * RBAC (Q23): Field hanya melihat Job yang ditugaskan kepadanya
 * (Job::scopeVisibleTo) dan TIDAK melihat angka keuangan — angka tunggakan
 * hanya dihitung + dikirim bila user punya ability `laporan_keuangan`
 * (peran Finance). Semua query di-scope company_id user.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DemurrageCalculator $calculator): Response
    {
        $user = $request->user();
        $canSeeFinance = $user->can('laporan_keuangan');

        $jobs = Job::query()
            ->where('company_id', $user->company_id)
            ->visibleTo($user);

        $activeJobs = (clone $jobs)->whereNull('closed_at');

        $jobsByStatus = (clone $activeJobs)
            ->selectRaw('cached_status, COUNT(*) as total')
            ->groupBy('cached_status')
            ->orderByDesc('total')
            ->pluck('total', 'cached_status')
            ->all();

        $flags = (clone $activeJobs)
            ->whereNotNull('problem_flag')
            ->selectRaw('problem_flag, COUNT(*) as total')
            ->groupBy('problem_flag')
            ->pluck('total', 'problem_flag')
            ->all();

        // Hitung mundur free time per Job aktif dengan DemurrageCalculator
        // yang sama seperti halaman detail Job. Tanpa Kurs Internal USD,
        // kalkulator melempar LogicException (TEMUAN #4): dashboard tetap
        // tampil dan hitungan Job itu ditandai tidak tersedia, bukan 500.
        $freeTimeStats = ['overdue' => 0, 'urgent' => 0, 'ok' => 0, 'unavailable' => 0];
        $freeTimeByJob = [];
        $activeList = (clone $activeJobs)
            ->with(['importir:id,name', 'containers'])
            ->orderByDesc('updated_at')
            ->get();

        foreach ($activeList as $job) {
            try {
                $calc = $calculator->calculateForJob($job);
            } catch (LogicException) {
                // Kalkulator gagal (kurs belum diatur). Tandai tidak
                // tersedia hanya bila Job ini memang punya jam yang
                // seharusnya berjalan (ada discharge / gate-out aktif);
                // bila tidak ada jam sama sekali, hitungannya null biasa.
                $freeTimeByJob[$job->id] = ['unavailable' => $this->jobMayHaveRunningClock($job)];
                if ($freeTimeByJob[$job->id]['unavailable']) {
                    $freeTimeStats['unavailable']++;
                }

                continue;
            }

            $worst = null;
            $running = 0;
            foreach ($calc['containers'] as $container) {
                $containerWorst = null;
                foreach (['demurrage', 'detention'] as $kind) {
                    $clock = $container[$kind];
                    if (! $clock['has_started'] || $clock['is_final']) {
                        continue;
                    }
                    $candidate = [
                        'remaining_days' => (int) $clock['remaining_free_days'],
                        'last_free_date' => $clock['last_free_date'],
                        'lampu' => $clock['lampu'],
                    ];
                    if ($containerWorst === null || $candidate['remaining_days'] < $containerWorst['remaining_days']) {
                        $containerWorst = $candidate;
                    }
                }
                if ($containerWorst === null) {
                    continue;
                }
                $running++;
                if ($containerWorst['remaining_days'] < 0) {
                    $freeTimeStats['overdue']++;
                } elseif ($containerWorst['remaining_days'] <= 3) {
                    $freeTimeStats['urgent']++;
                } else {
                    $freeTimeStats['ok']++;
                }
                if ($worst === null || $containerWorst['remaining_days'] < $worst['remaining_days']) {
                    $worst = $containerWorst;
                }
            }

            $freeTimeByJob[$job->id] = $worst === null ? null : [
                'remaining_days' => $worst['remaining_days'],
                'last_free_date' => $worst['last_free_date'],
                'lampu' => $worst['lampu'],
                'containers_running' => $running,
            ];
        }

        // Tunggakan invoice — angka keuangan, hanya untuk pemegang ability
        // laporan_keuangan (Finance). Selain itu prop bernilai null dan UI
        // tidak menampilkan kartunya sama sekali.
        $unpaid = null;
        if ($canSeeFinance) {
            $row = Invoice::query()
                ->where('company_id', $user->company_id)
                ->whereIn('job_id', (clone $jobs)->select('id'))
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->whereRaw('total_invoice > total_paid')
                ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(total_invoice - total_paid), 0) as total_outstanding')
                ->first();

            $unpaid = [
                'count' => (int) $row->invoice_count,
                'total' => (float) $row->total_outstanding,
            ];
        }

        $jobRows = $activeList->take(15)->map(function (Job $job) use ($freeTimeByJob) {
            $entry = $freeTimeByJob[$job->id] ?? null;

            return [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'importir_name' => $job->importir?->name,
                'cached_status' => $job->cached_status,
                'problem_flag' => $job->problem_flag,
                'free_time' => is_array($entry) && isset($entry['remaining_days']) ? $entry : null,
                'free_time_unavailable' => is_array($entry) && ($entry['unavailable'] ?? false),
            ];
        })->all();

        $activity = AuditTrail::query()
            ->where('company_id', $user->company_id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (AuditTrail $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'object_type' => $a->object_type,
                'object_id' => $a->object_id,
                'reason' => $a->reason,
                'user_name' => $a->user?->name,
                'created_at' => $a->created_at?->format('d-m-Y H:i'),
            ])
            ->all();

        return Inertia::render('dashboard', [
            'stats' => [
                'jobs_active' => (clone $activeJobs)->count(),
                'jobs_by_status' => $jobsByStatus,
                'jobs_with_flag' => array_sum($flags),
                'flags' => $flags,
                'free_time' => $freeTimeStats,
                'unread_notifications' => NotificationLog::unreadCountFor($user),
            ],
            'unpaid' => $unpaid,
            'can_see_finance' => $canSeeFinance,
            'jobs' => $jobRows,
            'activity' => $activity,
        ]);
    }

    /**
     * Apakah Job ini mungkin punya jam demurrage/detention yang berjalan —
     * dipakai saat kalkulator gagal (kurs belum diatur) untuk membedakan
     * "tidak ada jam berjalan" dari "hitungan tidak tersedia". Dicek dari
     * milestone pemicu jam (discharge / gate-out full), tanpa kalkulator.
     */
    private function jobMayHaveRunningClock(Job $job): bool
    {
        return $job->milestones()
            ->where('is_active', true)
            ->whereIn('milestone_type', [Milestone::TYPE_DISCHARGE, Milestone::TYPE_GATE_OUT_FULL])
            ->exists();
    }
}
