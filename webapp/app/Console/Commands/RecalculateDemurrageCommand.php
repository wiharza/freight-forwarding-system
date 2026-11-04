<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Services\DemurrageCalculator;
use Illuminate\Console\Command;

class RecalculateDemurrageCommand extends Command
{
    protected $signature = 'app:recalculate-demurrage';

    protected $description = 'Kalkulasi sisa waktu Free Time & denda Demurrage/Detention kontainer aktif jam 00:00 (§4.4, Q8)';

    public function handle(DemurrageCalculator $calculator): int
    {
        $this->info('Memulai perhitungan harian Demurrage & Detention jam 00:00...');

        // Ambil semua Job aktif yang belum Closed
        $activeJobs = Job::query()
            ->where('cached_status', '!=', Job::STATUS_CLOSED)
            ->where('cached_status', '!=', Job::STATUS_DRAFT)
            ->with(['shippingLine', 'containers', 'milestones'])
            ->get();

        $processedCount = 0;
        $overdueCount = 0;

        foreach ($activeJobs as $job) {
            $jobCalc = $calculator->calculateForJob($job);

            foreach ($jobCalc['containers'] as $res) {
                $processedCount++;
                if ($res['total_usd'] > 0) {
                    $overdueCount++;
                }
            }
        }

        $this->info("Perhitungan selesai: {$processedCount} kontainer diproses, {$overdueCount} kontainer mengalami keterlambatan/denda.");

        return Command::SUCCESS;
    }
}
