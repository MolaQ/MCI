<?php

namespace App\Livewire\Concerns;

use App\Enums\Feature;
use App\Models\Project;
use App\Support\CurrentSchool;
use App\Support\Features;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * "New task / request inside a project" (?projekt=ID): remembered on the
 * form and linked by LinkWork after saving. Ignored when the person cannot
 * work on that project from the active school.
 */
trait LinksToProject
{
    #[Locked]
    public ?int $linkProjectId = null;

    protected function rememberProject(): void
    {
        $id = (int) request()->query('projekt');
        if (! $id || app(Features::class)->disabled(Feature::Projects)) {
            return;
        }
        $project = Project::query()->find($id);
        if ($project && Auth::user()->can('work', $project) && in_array(app(CurrentSchool::class)->id(), $project->schoolIds(), true)) {
            $this->linkProjectId = $project->id;
        }
    }

    #[Computed]
    public function linkProject(): ?Project
    {
        return $this->linkProjectId ? Project::query()->find($this->linkProjectId) : null;
    }
}
