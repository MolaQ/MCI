<?php

namespace App\Models;

use App\Domain\Communication\Audience;
use App\Enums\ProjectKind;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * A school or inter-school project (Step 7): owner school + optional partner
 * schools, a coordinator and a team (people, groups, roles), milestones and
 * linked To-Do tasks / workflow requests.
 *
 * Visibility "school": every active member of a participating school;
 * "team": only the coordinator, the author and the team (plus managers).
 *
 * @property ProjectStatus $status
 * @property ProjectKind $kind
 */
class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'name', 'kind', 'description', 'status', 'visibility', 'starts_on', 'ends_on',
        'coordinator_id', 'budget_notes', 'cover_media_id', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'kind' => ProjectKind::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function partnerSchools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'project_schools');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('position')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function workflowRequests(): HasMany
    {
        return $this->hasMany(WorkflowRequest::class);
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'source');
    }

    /** @return list<int> owner school first, then partners */
    public function schoolIds(): array
    {
        return array_values(array_unique([$this->school_id, ...$this->partnerSchools()->pluck('schools.id')->all()]));
    }

    public function isCrossSchool(): bool
    {
        return $this->partnerSchools()->exists();
    }

    /**
     * Projects this person is part of: coordinator, author, or in the team
     * (directly, through a group, or a role they hold in the member's school).
     */
    public function scopeInvolving(Builder $query, User $user): void
    {
        $groupIds = $user->groups()->pluck('groups.id')->all();

        $query->where(fn (Builder $w) => $w
            ->where('projects.coordinator_id', $user->id)
            ->orWhere('projects.created_by', $user->id)
            ->orWhereExists(fn (QueryBuilder $q) => $q->from('project_members as pm')
                ->whereColumn('pm.project_id', 'projects.id')
                ->where(fn (QueryBuilder $m) => $m->where('pm.user_id', $user->id)
                    ->when($groupIds, fn ($g) => $g->orWhereIn('pm.group_id', $groupIds))
                    ->orWhereExists(fn (QueryBuilder $r) => $r->from('model_has_roles as mhr')
                        ->join('roles', 'roles.id', '=', 'mhr.role_id')
                        ->where('mhr.model_type', 'user')
                        ->where('mhr.model_id', $user->id)
                        ->whereColumn('mhr.school_id', 'pm.school_id')
                        ->whereColumn('roles.name', 'pm.role')))));
    }

    /**
     * Everything this person may open: projects they are involved in, every
     * school-visible project of a school where they are active, and — for
     * people who manage projects there — every project of $managedSchoolId.
     */
    public function scopeVisibleTo(Builder $query, User $user, ?int $managedSchoolId = null): void
    {
        $schoolIds = $user->schools()->wherePivot('status', 'active')->where('schools.is_active', true)->pluck('schools.id')->all();

        $query->where(function (Builder $w) use ($user, $schoolIds, $managedSchoolId) {
            $w->where(fn (Builder $q) => $q->involving($user))
                ->orWhere(fn (Builder $q) => $q->where('projects.visibility', 'school')->inSchools($schoolIds))
                ->when($managedSchoolId, fn ($q) => $q->orWhere(fn (Builder $m) => $m->inSchools([$managedSchoolId])));
        });
    }

    /** Owned by, or shared with, one of these schools. */
    public function scopeInSchools(Builder $query, array $schoolIds): void
    {
        $query->where(fn (Builder $w) => $w->whereIn('projects.school_id', $schoolIds ?: [0])
            ->orWhereExists(fn (QueryBuilder $q) => $q->from('project_schools as ps')
                ->whereColumn('ps.project_id', 'projects.id')->whereIn('ps.school_id', $schoolIds ?: [0])));
    }

    /**
     * Active people in the project: coordinator + team (groups and roles
     * resolved in their school).
     *
     * @return Collection<int, int>
     */
    public function teamIds(): Collection
    {
        $ids = collect([$this->coordinator_id]);
        foreach ($this->members()->with('school')->get()->groupBy('school_id') as $rows) {
            $ids = $ids->merge(Audience::fromKeys($rows->map->key()->all())->resolve($rows->first()->school));
        }

        return $ids->filter()->unique()->values();
    }

    /**
     * Milestones and linked tasks done vs. all (cancelled tasks do not count).
     *
     * @return array{milestones: array{done: int, total: int}, tasks: array{done: int, total: int}, percent: int}
     */
    public function progress(): array
    {
        $m = $this->relationLoaded('milestones') ? $this->milestones : $this->milestones()->get(['id', 'completed_at']);
        $t = $this->tasks()->toBase()->where('status', '!=', TaskStatus::Cancelled->value)
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as done', [TaskStatus::Done->value])->first();

        $milestones = ['done' => $m->whereNotNull('completed_at')->count(), 'total' => $m->count()];
        $tasks = ['done' => (int) ($t->done ?? 0), 'total' => (int) ($t->total ?? 0)];
        $total = $milestones['total'] + $tasks['total'];

        return [
            'milestones' => $milestones,
            'tasks' => $tasks,
            'percent' => $this->status === ProjectStatus::Completed ? 100 : ($total ? (int) round(100 * ($milestones['done'] + $tasks['done']) / $total) : 0),
        ];
    }

    public function nextMilestone(): ?ProjectMilestone
    {
        return $this->milestones()->whereNull('completed_at')->whereNotNull('due_at')->reorder('due_at')->first();
    }

    public function datesLabel(): ?string
    {
        $from = $this->starts_on?->translatedFormat('j M Y');
        $to = $this->ends_on?->translatedFormat('j M Y');

        return match (true) {
            $from && $to => $from.' – '.$to,
            (bool) $from => __('od :date', ['date' => $from]),
            (bool) $to => __('do :date', ['date' => $to]),
            default => null,
        };
    }
}
