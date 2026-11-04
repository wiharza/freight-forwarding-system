<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler otomatis jam 00:00 (§4.4, Q8, Tiket 08)
Schedule::command('app:recalculate-demurrage')->dailyAt('00:00');

// Alarm Free Time H-2/H-0/H+1 (§2.6, Q8, Tiket 15) — sesudah hitung ulang,
// supaya last free date yang dipakai sudah yang terbaru. 00:00 UTC =
// 07:00 WIB bila APP_TIMEZONE=UTC (lihat .env); bila APP_TIMEZONE diubah,
// jam dindingnya ikut berubah.
Schedule::command('notifications:check-free-time')->dailyAt('00:00');
