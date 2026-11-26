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

// Scheduled announcements and any delivery left pending (Step 3).
Schedule::command('mci:deliver-announcements')->everyMinute()->withoutOverlapping();

// To-Do reminders: due soon and overdue, idempotent via task_reminders (Step 5).
Schedule::command('mci:task-reminders')->everyFifteenMinutes()->withoutOverlapping();

// Workflow: approvers past the stage deadline get one reminder a day (Step 6).
Schedule::command('mci:workflow-reminders')->hourly()->withoutOverlapping();
