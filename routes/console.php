<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('integrations:dispatch-due-deliveries')->everyMinute()->withoutOverlapping();
Schedule::command('domains:reconcile')->everyFiveMinutes()->withoutOverlapping();
