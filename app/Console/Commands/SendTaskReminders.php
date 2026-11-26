<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use App\Support\Features;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled every 15 minutes (docs/ARCHITECTURE.md §5.3):
 *  - due_soon: once per task and person, when the reminder window opens
 *    (the task's own "remind before", else the default);
 *  - overdue:  once a day per task and person, only in daytime hours.
 * Every send is first written to task_reminders (unique per day), so a
 * repeated or overlapping run never notifies twice.
 */
#[Signature('mci:task-reminders')]
#[Description('Send due-soon and overdue task reminders')]
class SendTaskReminders extends Command
{
    public function handle(Features $features): int
    {
        $now = now();
        $off = $features->schoolsWithout(Feature::TaskReminders)->merge($features->schoolsWithout(Feature::Tasks))->unique()->all();
        $siteOff = $features->disabled(Feature::TaskReminders, null) || $features->disabled(Feature::Tasks, null);
        if ($siteOff) {
            return self::SUCCESS;
        }

        $maxWindow = max(config('mci.tasks.remind_before_minutes'), config('mci.tasks.remind_before_minutes_all_day'), 10080);
        $sent = 0;

        Task::query()->open()->with('school')
            ->whereNotIn('school_id', $off)
            ->whereBetween('due_at', [$now, $now->copy()->addMinutes($maxWindow)])
            ->each(function (Task $task) use ($now, &$sent) {
                $window = $task->remind_before_minutes
                    ?? ($task->all_day ? config('mci.tasks.remind_before_minutes_all_day') : config('mci.tasks.remind_before_minutes'));
                if ($task->due_at->copy()->subMinutes($window)->lte($now)) {
                    $sent += $this->remind($task, 'due_soon', once: true);
                }
            });

        [$from, $to] = config('mci.tasks.overdue_hours');
        if ($now->hour >= $from && $now->hour < $to) {
            Task::query()->overdue()->with('school')
                ->whereNotIn('school_id', $off)
                ->where('due_at', '>=', $now->copy()->subDays(30))
                ->each(function (Task $task) use (&$sent) {
                    $sent += $this->remind($task, 'overdue', once: false);
                });
        }

        $this->info("Sent {$sent} task reminder(s).");

        return self::SUCCESS;
    }

    private function remind(Task $task, string $type, bool $once): int
    {
        $count = 0;
        foreach ($task->recipientIds() as $userId) {
            if ($once && DB::table('task_reminders')->where(['task_id' => $task->id, 'user_id' => $userId, 'type' => $type])->exists()) {
                continue;
            }
            $claimed = DB::table('task_reminders')->insertOrIgnore([
                'task_id' => $task->id, 'user_id' => $userId, 'type' => $type,
                'sent_on' => now()->toDateString(), 'sent_at' => now(),
            ]);
            if ($claimed !== 1) {
                continue;
            }
            $user = User::query()->with('pushSubscriptions')->active()->find($userId);
            try {
                $user?->notify(new TaskNotification($task, $type));
                $count++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $count;
    }
}
