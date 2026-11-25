<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler — the only cron entry MCI needs:
|   * * * * * cd /path/to/mci && php artisan schedule:run >> /dev/null 2>&1
|--------------------------------------------------------------------------
*/

if (config('mci.queue_via_scheduler')) {
    // Shared hosting: drain the database queue every minute instead of a
    // permanently running worker. Alerts are processed first.
    Schedule::command('queue:work', [
        '--queue' => implode(',', config('mci.queues')),
        '--stop-when-empty' => true,
        '--max-time' => config('mci.queue_max_time'),
        '--tries' => 3,
    ])->everyMinute()->withoutOverlapping(5)->runInBackground();
}

Schedule::command('queue:prune-failed', ['--hours' => 24 * 14])->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
