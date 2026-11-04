<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Job;
use App\Services\DemurrageCalculator;
use App\Support\CompanyContext;
use Illuminate\Console\Command;

class RecalculateDemurrageCommand extends Command
{
    protected $signature = 'app:recalculate-demurrage';

    protected $description = 'Kalkulasi sisa waktu Free Time & denda Demurrage/Detention kontainer aktif jam 00:00 (§4.4, Q8)';

    public function handle(DemurrageCalculator $calculator): int
    {
        $this->info('Memulai perhitungan harian Demurrage & Detention jam 00:00...');

        $processedCount = 0;
        $overdueCount = 0;

        // AUDIT: global scope BelongsToCompany membaca CompanyContext yang di
        // konteks console jatuh ke company pertama (min id) — tenant lain
        // terlewati diam-diam. Iterasi eksplisit per company dengan context di-set.
        foreach (Company::query()->orderBy('id')->get() as $company) {
            app(CompanyContext::class)->set($company->id);

            $activeJobs = Job::query()
                ->where('company_id', $company->id)
                ->where('cached_status', '!=', Job::STATUS_CLOSED)
                ->where('cached_status', '!=', Job::STATUS_DRAFT)
                ->with(['shippingLine', 'containers', 'milestones'])
                ->get();

            foreach ($activeJobs as $job) {
                $jobCalc = $calculator->calculateForJob($job);

                foreach ($jobCalc['containers'] as $res) {
                    $processedCount++;
                    if ($res['total_usd'] > 0) {
                        $overdueCount++;
                    }
                }
            }
        }

        $this->info("Perhitungan selesai: {$processedCount} kontainer diproses, {$overdueCount} kontainer mengalami keterlambatan/denda.");

        return Command::SUCCESS;
    }
}
