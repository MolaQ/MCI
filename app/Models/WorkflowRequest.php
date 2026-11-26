<?php

namespace App\Models;

use App\Domain\Communication\Audience;
use App\Enums\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * A submitted request moving through the frozen copy of its template's
 * stages. approver_* always describe who decides at the current stage.
 *
 * @property WorkflowStatus $status
 * @property list<array{name: string, approver_type: string, approver_id: string, school_id: ?int, sla_hours: ?int,
 *                     task_on_reach: bool, followup_task: ?string, approver_label: string}> $stages
 */
class WorkflowRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_id', 'school_id', 'requester_id', 'title', 'template_name', 'data', 'stages', 'status',
        'current_position', 'approver_type', 'approver_id', 'approver_school_id', 'stage_due_at', 'reminded_at',
        'submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'data' => 'array',
            'stages' => 'array',
            'current_position' => 'integer',
            'stage_due_at' => 'datetime',
            'reminded_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'template_id')->withTrashed();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function approverSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'approver_school_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class, 'request_id')->orderBy('id');
    }

    /** Files attached when submitting (decision images belong to the actions). */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'owner')->where('collection', 'image')->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'workflow_request_id');
    }

    public function currentStage(): ?array
    {
        return $this->stages[$this->current_position] ?? null;
    }

    /** Requests waiting for this person's decision (directly, via a group or a role). */
    public function scopeAwaitingDecisionBy(Builder $query, User $user): void
    {
        $groupIds = $user->groups()->pluck('groups.id')->map(fn ($id) => (string) $id)->all();

        $query->where('workflow_requests.status', WorkflowStatus::InProgress->value)
            ->where(function (Builder $w) use ($user, $groupIds) {
                $w->where(fn ($q) => $q->where('workflow_requests.approver_type', 'user')->where('workflow_requests.approver_id', (string) $user->id))
                    ->when($groupIds, fn ($q) => $q->orWhere(fn ($g) => $g->where('workflow_requests.approver_type', 'group')->whereIn('workflow_requests.approver_id', $groupIds)))
                    ->orWhere(fn ($q) => $q->where('workflow_requests.approver_type', 'role')
                        ->whereExists(fn (QueryBuilder $r) => $r->from('model_has_roles as mhr')
                            ->join('roles', 'roles.id', '=', 'mhr.role_id')
                            ->join('school_user as su', fn ($j) => $j->on('su.school_id', '=', 'mhr.school_id')->on('su.user_id', '=', 'mhr.model_id'))
                            ->where('su.status', 'active')
                            ->where('mhr.model_type', 'user')
                            ->where('mhr.model_id', $user->id)
                            ->whereColumn('mhr.school_id', 'workflow_requests.approver_school_id')
                            ->whereColumn('roles.name', 'workflow_requests.approver_id')));
            })
            ->where(fn ($q) => $q->whereNull('workflow_requests.requester_id')->orWhere('workflow_requests.requester_id', '!=', $user->id));
    }

    /** Everything this person may open: theirs, awaiting them, or decided by them. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $w) use ($user) {
            $w->where('workflow_requests.requester_id', $user->id)
                ->orWhere(fn ($q) => $q->awaitingDecisionBy($user))
                ->orWhereExists(fn (QueryBuilder $a) => $a->from('workflow_actions')
                    ->whereColumn('workflow_actions.request_id', 'workflow_requests.id')
                    ->where('workflow_actions.user_id', $user->id));
        });
    }

    /** Keys of the current approvers, for Audience: user:1, group:2, role:teacher. */
    public function approverKey(): ?string
    {
        return $this->approver_type ? $this->approver_type.':'.$this->approver_id : null;
    }

    /**
     * Active people who may decide at the current stage (never the requester).
     *
     * @return Collection<int, int>
     */
    public function approverIds(): Collection
    {
        if (! $this->approverKey() || ! $this->approver_school_id) {
            return collect();
        }

        return Audience::fromKeys([$this->approverKey()])->resolve($this->approverSchool)
            ->reject(fn ($id) => $id === $this->requester_id)->values();
    }

    public function isOverdue(): bool
    {
        return $this->status === WorkflowStatus::InProgress && $this->stage_due_at !== null && $this->stage_due_at->isPast();
    }
}
