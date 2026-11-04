<?php

namespace App\Console\Commands;

use App\Services\FreeTimeAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Perintah harian alarm Free Time (§2.6, Q8, Tiket 15) — dijalankan
 * scheduler tiap 00:00 (07:00 WIB bila APP_TIMEZONE=UTC). Opsi --date
 * untuk simulasi tanggal (dipakai juga oleh tes): pemicu H-2/H-0/H+1
 * dievaluasi seolah-olah "hari ini" adalah tanggal tersebut.
 */
class CheckFreeTimeNotificationsCommand extends Command
{
    protected $signature = 'notifications:check-free-time
        {--date= : Tanggal simulasi (Y-m-d), default hari ini}';

    protected $description = 'Kirim alarm Free Time H-2/H-0/H+1 ke PIC Job + Finance (§2.6, Q8, Tiket 15)';

    public function handle(FreeTimeAlertService $service): int
    {
        $asOf = now();

        if ($this->option('date')) {
            try {
                $asOf = Carbon::createFromFormat('Y-m-d', $this->option('date'))->startOfDay();
            } catch (\Exception) {
                $this->error('Opsi --date harus berformat Y-m-d, contoh: --date=2026-11-10.');

                return Command::FAILURE;
            }
        }

        $summary = $service->checkAll($asOf);

        $this->info("Alarm Free Time per {$asOf->toDateString()}: {$summary['jobs']} Job, {$summary['containers']} kontainer dievaluasi, {$summary['alerts']} pemicu berbunyi, {$summary['errors']} Job gagal dievaluasi.");

        return $summary['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
