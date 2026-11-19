<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:health --quiet-ok')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('app:weekly-report')->weeklyOn(1, '08:00');
Schedule::command('model:prune')->daily();
