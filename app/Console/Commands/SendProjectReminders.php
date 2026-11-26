<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Enums\ProjectStatus;
use App\Jobs\NotifyProject;
use App\Models\ProjectMilestone;
use App\Support\Features;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hourly: milestones of open projects get one "due soon" notice (24 h
 * before the deadline) and one "overdue" notice, in daytime hours only
 * (Europe/Warsaw). The flags on the milestone make it idempotent; a new
 * deadline resets them.
 */
#[Signature('mci:project-reminders')]
#[Description('Remind project teams about milestone deadlines')]
class SendProjectReminders extends Command
{
    public function handle(Features $features): int
    {
        [$from, $to] = config('mci.tasks.overdue_hours');
        if (now()->hour < $from || now()->hour >= $to || $features->disabled(Feature::ProjectReminders, null)) {
            return self::SUCCESS;
        }
        $off = $features->schoolsWithout(Feature::ProjectReminders)->all();
        $sent = 0;

        $base = fn () => ProjectMilestone::query()->whereNull('completed_at')->whereNotNull('due_at')
            ->whereHas('project', fn ($q) => $q->whereIn('status', [ProjectStatus::Planned->value, ProjectStatus::InProgress->value])
                ->whereNotIn('school_id', $off));

        $base()->whereNull('reminded_at')->whereBetween('due_at', [now(), now()->addDay()])->with('project')
            ->each(function (ProjectMilestone $m) use (&$sent) {
                $m->forceFill(['reminded_at' => now()])->save();
                $sent += $this->notify($m, 'milestone_due');
            });

        $base()->whereNull('overdue_notified_at')->where('due_at', '<', now())->with('project')
            ->each(function (ProjectMilestone $m) use (&$sent) {
                $m->forceFill(['overdue_notified_at' => now(), 'reminded_at' => $m->reminded_at ?? now()])->save();
                $sent += $this->notify($m, 'milestone_overdue');
            });

        $this->info("Sent {$sent} milestone reminder(s).");

        return self::SUCCESS;
    }

    private function notify(ProjectMilestone $milestone, string $kind): int
    {
        $ids = $milestone->project->teamIds()->all();
        if (! $ids) {
            return 0;
        }
        NotifyProject::dispatch($milestone->project_id, $ids, $kind, null, $milestone->id);

        return 1;
    }
}
