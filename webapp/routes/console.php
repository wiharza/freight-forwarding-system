<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler otomatis jam 00:00 (§4.4, Q8, Tiket 08)
Schedule::command('app:recalculate-demurrage')->dailyAt('00:00');
