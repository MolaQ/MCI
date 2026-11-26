<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Bell + Web Push for one task event (assigned, mention, completed,
 * cancelled). People who can no longer see the task are skipped.
 */
class NotifyTask implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(public int $taskId, public array $userIds, public string $kind, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $task = Task::query()->with(['school', 'creator'])->find($this->taskId);
        if (! $task) {
            return;
        }
        $actor = $this->actorId ? User::query()->find($this->actorId) : null;

        User::query()->with('pushSubscriptions')->whereKey($this->userIds)->active()->get()
            ->each(function (User $user) use ($task, $actor) {
                try {
                    $user->notify(new TaskNotification($task, $this->kind, $actor));
                } catch (\Throwable $e) {
                    // One unreachable push service must not stop the others.
                    report($e);
                }
            });
    }
}
