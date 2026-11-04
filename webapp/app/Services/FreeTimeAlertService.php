<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Container;
use App\Models\FreeTimeAlert;
use App\Models\Job;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Evaluasi pemicu alarm Free Time (§2.6, Q8, Tiket 15).
 *
 * Per kontainer, per jenis biaya (demurrage & detention punya jam free time
 * sendiri): H-2 saat sisa 2 hari, H-0 saat sisa 0 hari, H+1 saat sudah
 * lewat 1 hari. Pemicu hanya berbunyi pada tanggalnya persis — tidak
 * retroaktif (pemicu yang tanggalnya sudah lewat tidak dikejar), dan
 * hanya untuk jam yang masih berjalan (belum final: demurrage belum
 * gate-out, detention belum gate-in). Idempotency dijamin baris
 * free_time_alerts (unique per kontainer + jenis + pemicu + tanggal
 * free time): bila tanggal free time berubah karena koreksi milestone,
 * pemicu untuk tanggal baru boleh berbunyi lagi.
 *
 * Job tertutup dan Job draf tidak dievaluasi.
 */
class FreeTimeAlertService
{
    public function __construct(
        protected DemurrageCalculator $calculator,
        protected NotificationService $notifications,
    ) {}

    /**
     * Evaluasi semua Job aktif di semua company. Mengembalikan ringkasan
     * ['jobs' => n, 'containers' => n, 'alerts' => n, 'errors' => n].
     * Satu Job yang gagal (mis. kurs belum diatur) tidak menghentikan
     * Job lain — dicatat dan dihitung sebagai error.
     */
    public function checkAll(?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf) : now();
        $summary = ['jobs' => 0, 'containers' => 0, 'alerts' => 0, 'errors' => 0];

        foreach (Company::query()->orderBy('id')->get() as $company) {
            // AUDIT P1-2: Job & relasinya membawa global scope BelongsToCompany yang
            // membaca CompanyContext. Di konteks console tidak ada middleware yang
            // men-set context, sehingga scope jatuh ke company pertama (min id) dan
            // alarm diam-diam tidak pernah berbunyi untuk tenant lain. Set context
            // eksplisit per company untuk setiap iterasi.
            app(\App\Support\CompanyContext::class)->set($company->id);

            $jobs = Job::query()
                ->where('company_id', $company->id)
                ->where('cached_status', '!=', Job::STATUS_CLOSED)
                ->where('cached_status', '!=', Job::STATUS_DRAFT)
                ->with(['shippingLine', 'containers', 'milestones', 'creator'])
                ->get();

            foreach ($jobs as $job) {
                $summary['jobs']++;
                $summary['containers'] += $job->containers->count();

                try {
                    $summary['alerts'] += $this->checkForJob($job, $asOf);
                } catch (\Throwable $e) {
                    $summary['errors']++;
                    Log::warning('Evaluasi alarm Free Time gagal untuk Job', [
                        'job_id' => $job->id,
                        'job_number' => $job->job_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $summary;
    }

    /**
     * Evaluasi satu Job pada tanggal referensi. Mengembalikan jumlah
     * pemicu yang berbunyi.
     */
    public function checkForJob(Job $job, ?CarbonInterface $asOf = null): int
    {
        if ($job->isClosed()) {
            return 0;
        }

        $asOf = $asOf ? Carbon::parse($asOf) : now();
        $fired = 0;

        foreach ($job->containers as $container) {
            $calc = $this->calculator->calculateForContainer($container, $asOf);

            $fired += $this->evaluateClock($job, $container, FreeTimeAlert::CHARGE_DEMURRAGE, $calc['demurrage'], $calc, $asOf);
            $fired += $this->evaluateClock($job, $container, FreeTimeAlert::CHARGE_DETENTION, $calc['detention'], $calc, $asOf);
        }

        return $fired;
    }

    /**
     * Evaluasi satu jam free time (demurrage atau detention). Mengembalikan
     * 1 bila pemicu berbunyi, 0 bila tidak.
     */
    protected function evaluateClock(Job $job, Container $container, string $chargeType, array $section, array $calc, Carbon $asOf): int
    {
        if (empty($section['has_started']) || ! empty($section['is_final']) || empty($section['last_free_date'])) {
            return 0;
        }

        $freeTimeDate = Carbon::parse($section['last_free_date'])->startOfDay();
        $remaining = (int) $asOf->copy()->startOfDay()->diffInDays($freeTimeDate, false);

        $trigger = array_search($remaining, FreeTimeAlert::TRIGGER_REMAINING_DAYS, true);

        if ($trigger === false) {
            return 0;
        }

        // Klaim idempotency dulu: firstOrCreate + unique DB. Bila baris
        // sudah ada (pernah berbunyi untuk tanggal free time ini), diam.
        // Balapan dua proses yang sama-sama mengklaim ditangkap lewat
        // unique constraint — proses yang kalah diam saja.
        try {
            $alert = FreeTimeAlert::query()->firstOrCreate(
                [
                    'company_id' => $job->company_id,
                    'container_id' => $container->id,
                    'charge_type' => $chargeType,
                    'trigger' => $trigger,
                    'free_time_date' => $freeTimeDate->toDateString(),
                ],
                [
                    'job_id' => $job->id,
                    'status' => FreeTimeAlert::STATUS_SENT,
                    'sent_at' => now(),
                ]
            );
        } catch (QueryException) {
            return 0;
        }

        if (! $alert->wasRecentlyCreated) {
            return 0;
        }

        $result = $this->notifications->notifyFreeTimeAlert($job, $container, $chargeType, $trigger, $calc, $asOf);

        if (! empty($result['failed'])) {
            $alert->update(['status' => FreeTimeAlert::STATUS_FAILED]);
        }

        return 1;
    }
}
