<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\ProjectGuard;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Soft delete; linked tasks and requests stay, just without the project. */
class DeleteProject
{
    public function handle(User $actor, Project $project): void
    {
        Gate::forUser($actor)->authorize('delete', $project);

        ProjectGuard::audit($project, 'project.deleted', [], $actor);
        DB::transaction(function () use ($project) {
            $project->tasks()->update(['project_id' => null, 'project_milestone_id' => null]);
            $project->workflowRequests()->update(['project_id' => null]);
            $project->delete();
        });
    }
}
