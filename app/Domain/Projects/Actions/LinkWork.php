<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\ProjectGuard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Links To-Do tasks and workflow requests to a project (and a task to one
 * of its milestones). Only work from a participating school that the
 * person can already see can be linked.
 */
class LinkWork
{
    public function task(User $actor, Project $project, Task $task, ?int $milestoneId = null): void
    {
        Gate::forUser($actor)->authorize('work', $project);
        Gate::forUser($actor)->authorize('view', $task);
        if ($task->is_personal || ! in_array($task->school_id, $project->schoolIds(), true)) {
            throw ValidationException::withMessages(['link' => __('To zadanie nie może należeć do tego projektu.')]);
        }
        $milestoneId = $milestoneId && $project->milestones()->whereKey($milestoneId)->exists() ? $milestoneId : null;

        $task->update(['project_id' => $project->id, 'project_milestone_id' => $milestoneId]);
        ProjectGuard::audit($project, 'project.linked', ['task' => $task->title, 'task_id' => $task->id], $actor);
    }

    public function request(User $actor, Project $project, WorkflowRequest $request): void
    {
        Gate::forUser($actor)->authorize('work', $project);
        Gate::forUser($actor)->authorize('view', $request);
        if (! in_array($request->school_id, $project->schoolIds(), true)) {
            throw ValidationException::withMessages(['link' => __('Ten wniosek nie może należeć do tego projektu.')]);
        }

        $request->update(['project_id' => $project->id]);
        ProjectGuard::audit($project, 'project.linked', ['request' => $request->title, 'request_id' => $request->id], $actor);
    }

    public function unlinkTask(User $actor, Project $project, Task $task): void
    {
        Gate::forUser($actor)->authorize('work', $project);
        abort_unless($task->project_id === $project->id, 404);

        $task->update(['project_id' => null, 'project_milestone_id' => null]);
        ProjectGuard::audit($project, 'project.unlinked', ['task' => $task->title, 'task_id' => $task->id], $actor);
    }

    public function unlinkRequest(User $actor, Project $project, WorkflowRequest $request): void
    {
        Gate::forUser($actor)->authorize('work', $project);
        abort_unless($request->project_id === $project->id, 404);

        $request->update(['project_id' => null]);
        ProjectGuard::audit($project, 'project.unlinked', ['request' => $request->title, 'request_id' => $request->id], $actor);
    }
}
