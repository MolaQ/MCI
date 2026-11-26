<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentSchool;

/**
 * Personal To-Do is visible only to its author. A shared task is visible
 * to its author, its assignees (directly, through a group or a role), the
 * people @mentioned in it and, for oversight, to people who may assign
 * tasks to anyone in that school.
 */
class TaskPolicy
{
    public function create(User $actor): bool
    {
        return app(CurrentSchool::class)->id() !== null;
    }

    public function view(User $actor, Task $task): bool
    {
        return $task->created_by === $actor->id
            || $this->isAssignee($actor, $task)
            || (! $task->is_personal && ($this->manages($actor, $task) || $this->isMentioned($actor, $task)));
    }

    public function update(User $actor, Task $task): bool
    {
        return $task->created_by === $actor->id || (! $task->is_personal && $this->manages($actor, $task));
    }

    /** Status and checklist: the author, any assignee and managers. */
    public function work(User $actor, Task $task): bool
    {
        return $this->update($actor, $task) || $this->isAssignee($actor, $task);
    }

    public function delete(User $actor, Task $task): bool
    {
        return $this->update($actor, $task);
    }

    private function isAssignee(User $actor, Task $task): bool
    {
        return Task::query()->whereKey($task->id)->assignedTo($actor)->exists();
    }

    private function isMentioned(User $actor, Task $task): bool
    {
        return $task->mentions()->where('mentionable_type', 'user')->where('mentionable_id', $actor->id)->exists();
    }

    private function manages(User $actor, Task $task): bool
    {
        return app(CurrentSchool::class)->id() === $task->school_id
            && $actor->can(Permission::TasksAssignAny->value);
    }
}
