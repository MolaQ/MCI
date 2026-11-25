<?php

namespace App\Domain\Tasks;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use App\Support\Features;
use Illuminate\Support\Collection;

/**
 * Who may assign a task to whom (docs/ARCHITECTURE.md §4.3):
 *  - everyone: personal To-Do and tasks for themselves;
 *  - tasks.assign.any: any person, group or role of the school;
 *  - a group leader: their group, or any member of a group they lead.
 * Group and role assignments also need the "tasks.teams" feature.
 * Used by the form and, never trusting it, by SaveTask.
 */
class TaskGuard
{
    public function assignsAny(User $actor): bool
    {
        return $actor->can(Permission::TasksAssignAny->value);
    }

    public function teamsEnabled(School $school): bool
    {
        return app(Features::class)->enabled(Feature::TaskTeams, $school);
    }

    public function canAssignOthers(User $actor, School $school): bool
    {
        return $this->assignsAny($actor) || $this->ledGroups($actor, $school)->isNotEmpty();
    }

    public function canAssignUser(User $actor, User $user, School $school): bool
    {
        if ($actor->is($user) || $this->assignsAny($actor)) {
            return true;
        }

        $led = $this->ledGroups($actor, $school)->pluck('id');

        return $led->isNotEmpty() && $user->groups()->whereIn('groups.id', $led)->exists();
    }

    public function canAssignGroup(User $actor, Group $group, School $school): bool
    {
        return $group->school_id === $school->id && $this->teamsEnabled($school)
            && ($this->assignsAny($actor) || $actor->leadsGroup($group));
    }

    public function canAssignRole(User $actor, School $school): bool
    {
        return $this->teamsEnabled($school) && $this->assignsAny($actor);
    }

    /** Groups of the school this person may assign whole tasks to. */
    public function assignableGroups(User $actor, School $school): Collection
    {
        if (! $this->teamsEnabled($school)) {
            return collect();
        }

        return $this->assignsAny($actor)
            ? Group::query()->where('school_id', $school->id)->orderBy('name')->get()
            : $this->ledGroups($actor, $school);
    }

    private function ledGroups(User $actor, School $school): Collection
    {
        return $actor->ledGroups()->where('groups.school_id', $school->id)->orderBy('name')->get();
    }
}
