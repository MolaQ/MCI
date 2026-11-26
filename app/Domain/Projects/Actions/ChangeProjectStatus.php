<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\ProjectGuard;
use App\Enums\ProjectStatus;
use App\Jobs\NotifyProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** One-click status change; the team hears about it (bell + push). */
class ChangeProjectStatus
{
    public function handle(User $actor, Project $project, ProjectStatus $status): Project
    {
        Gate::forUser($actor)->authorize('update', $project);

        if ($project->status === $status) {
            return $project;
        }
        $from = $project->status;
        $project->update(['status' => $status, 'completed_at' => $status === ProjectStatus::Completed ? now() : null]);

        ProjectGuard::audit($project, 'project.status', ['from' => $from->value, 'to' => $status->value], $actor);

        $team = $project->teamIds()->reject(fn ($id) => $id === $actor->id)->values()->all();
        if ($team) {
            NotifyProject::dispatch($project->id, $team, 'status', $actor->id);
        }

        return $project;
    }
}
