<?php

namespace App\Policies;

use App\Domain\Projects\ProjectGuard;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentSchool;

/**
 * View: the team, the coordinator and the author always; everyone active in
 * a participating school when the project is school-visible; people who
 * manage projects in the active school, for every project of that school.
 * Edit: the author, the coordinator and managers of the owner school.
 * Work (tick milestones, link tasks/requests): also the team.
 */
class ProjectPolicy
{
    public function create(User $actor): bool
    {
        return app(ProjectGuard::class)->canCreate($actor, app(CurrentSchool::class)->get());
    }

    public function view(User $actor, Project $project): bool
    {
        return Project::query()->whereKey($project->id)->visibleTo($actor, $this->managedSchool($actor))->exists();
    }

    public function update(User $actor, Project $project): bool
    {
        return $project->created_by === $actor->id
            || $project->coordinator_id === $actor->id
            || $this->managedSchool($actor) === $project->school_id;
    }

    public function work(User $actor, Project $project): bool
    {
        return $this->update($actor, $project)
            || Project::query()->whereKey($project->id)->involving($actor)->exists();
    }

    public function delete(User $actor, Project $project): bool
    {
        return $project->created_by === $actor->id || $this->managedSchool($actor) === $project->school_id;
    }

    /** The active school, when this person manages projects there. */
    private function managedSchool(User $actor): ?int
    {
        $school = app(CurrentSchool::class)->id();

        return $school && app(ProjectGuard::class)->manages($actor) ? $school : null;
    }
}
