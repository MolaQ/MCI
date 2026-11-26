<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Notifications\ProjectNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Gate;

/**
 * Bell + Web Push for one project event (assigned, coordinator, mention,
 * status, milestone_due, milestone_overdue). People who can no longer see
 * the project are skipped.
 */
class NotifyProject implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(public int $projectId, public array $userIds, public string $kind, public ?int $actorId = null, public ?int $milestoneId = null)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $project = Project::query()->with('school')->find($this->projectId);
        if (! $project) {
            return;
        }
        $actor = $this->actorId ? User::query()->find($this->actorId) : null;
        $milestone = $this->milestoneId ? ProjectMilestone::query()->find($this->milestoneId) : null;

        User::query()->with('pushSubscriptions')->whereKey($this->userIds)->active()->get()
            ->filter(fn (User $user) => Project::query()->whereKey($project->id)->visibleTo($user)->exists() || Gate::forUser($user)->allows('update', $project))
            ->each(function (User $user) use ($project, $actor, $milestone) {
                try {
                    $user->notify(new ProjectNotification($project, $this->kind, $actor, $milestone));
                } catch (\Throwable $e) {
                    // One unreachable push service must not stop the others.
                    report($e);
                }
            });
    }
}
