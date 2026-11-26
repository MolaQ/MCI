<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WorkflowRequest;
use App\Notifications\WorkflowNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Bell + Web Push for one workflow event (pending, returned, approved, rejected, mention, overdue). */
class NotifyWorkflow implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(public int $requestId, public array $userIds, public string $kind, public ?int $actorId = null)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $request = WorkflowRequest::query()->with(['school', 'requester'])->find($this->requestId);
        if (! $request) {
            return;
        }
        $actor = $this->actorId ? User::query()->find($this->actorId) : null;

        User::query()->with('pushSubscriptions')->whereKey($this->userIds)->active()->get()
            ->each(function (User $user) use ($request, $actor) {
                try {
                    $user->notify(new WorkflowNotification($request, $this->kind, $actor));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}
