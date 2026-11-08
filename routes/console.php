<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:generate')->dailyAt('01:00')->withoutOverlapping()
    ->description('Create the orders of the automatic deliveries that are due');
Schedule::command('app:verify-audit-log')->dailyAt('02:00')->withoutOverlapping()
    ->description('Check that the audit journal chain is intact');
Schedule::command('app:prune-access-logs')->dailyAt('03:00')->withoutOverlapping()
    ->description('Delete the access journal lines older than the retention period');
Schedule::command('app:backup')->dailyAt('01:30')->withoutOverlapping()
    ->description('Back up the database and the private files in an encrypted archive, then apply the retention rules');
Schedule::command('app:archive-audit-log')->monthlyOn(1, '04:00')->withoutOverlapping()
    ->description('Copy the audit journal of the month that just ended');
Schedule::command('app:verify-audit-archive')->dailyAt('02:30')->withoutOverlapping()
    ->description('Check the monthly copies of the audit journal against their manifests');
Schedule::command('app:prune-audit-archive')->dailyAt('03:30')->withoutOverlapping()
    ->description('Delete the oldest audit journal copies by age or when the disk is too full');
