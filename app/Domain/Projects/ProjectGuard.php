<?php

namespace App\Domain\Projects;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Models\Group;
use App\Models\Project;
use App\Models\School;
use App\Models\User;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Support\Collection;

/**
 * Rules shared by the project screens and actions (never trusting the form):
 *  - people: any active member of a participating school;
 *  - groups: managers pick any group of a participating school, others the
 *    groups they belong to;
 *  - roles (e.g. all teachers of the school): managers only.
 */
class ProjectGuard
{
    public function __construct(private Features $features) {}

    public function manages(User $actor): bool
    {
        return $actor->can(Permission::ProjectsManage->value);
    }

    public function canCreate(User $actor, ?School $school): bool
    {
        return $school !== null && $this->features->enabled(Feature::Projects, $school)
            && ($this->manages($actor) || $actor->can(Permission::EventsCreate->value));
    }

    public function crossSchool(School $school): bool
    {
        return $this->features->enabled(Feature::ProjectCrossSchool, $school);
    }

    /** Active schools that may be invited as partners. */
    public function partnerOptions(School $school): Collection
    {
        if (! $this->crossSchool($school)) {
            return collect();
        }

        return School::query()->where('is_active', true)->whereKeyNot($school->id)->orderBy('name')->get(['id', 'name', 'short_name', 'color', 'logo_media_id']);
    }

    /** Groups the actor may put into the team, across the participating schools. */
    public function groupOptions(User $actor, array $schoolIds): Collection
    {
        return Group::query()->whereIn('school_id', $schoolIds)
            ->unless($this->manages($actor), fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $actor->id)))
            ->with('school:id,short_name')->orderBy('name')->get();
    }

    /**
     * Writes the entry to the log of every participating school ("Logi
     * szkoły"); shared projects also land in the site journal.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function audit(Project $project, string $action, array $properties, User $actor): void
    {
        $properties = ['name' => $project->name] + $properties;
        $schools = $project->schoolIds();
        foreach ($schools as $schoolId) {
            Audit::log($action, $project, $properties + ($schoolId !== $project->school_id ? ['partner' => true] : []), $actor, $schoolId);
        }
        if (count($schools) > 1) {
            Audit::site($action, $project, $properties + ['schools' => $schools], $actor);
        }
    }
}
