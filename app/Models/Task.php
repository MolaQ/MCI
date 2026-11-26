<?php

namespace App\Models;

use App\Domain\Communication\Audience;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Personal To-Do or a task assigned to people, groups (teams) or roles of
 * one school (docs/ARCHITECTURE.md §3.6). A group or role assignment means
 * "any member": everyone sees it and one completion completes it.
 *
 * @property TaskStatus $status
 * @property TaskPriority $priority
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'event_id', 'created_by', 'title', 'description', 'is_personal', 'priority', 'status',
        'due_at', 'all_day', 'remind_before_minutes', 'completed_at', 'completed_by', 'position',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'is_personal' => 'boolean',
            'all_day' => 'boolean',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'remind_before_minutes' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(TaskAssignee::class);
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    /** Images attached to the task, in order. */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'owner')->where('collection', 'image')->orderBy('position');
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'source');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('tasks.status', TaskStatus::openValues());
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('tasks.due_at')->where('tasks.due_at', '<', now());
    }

    /**
     * Tasks on this person's own list: their personal To-Do plus everything
     * assigned to them directly, to a group they belong to or to a role they
     * hold in the task's school. Only schools where they are active count.
     */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $groupIds = $user->groups()->pluck('groups.id')->all();

        $query->whereIn('tasks.school_id', $user->schools()->wherePivot('status', 'active')->pluck('schools.id'))
            ->where(function (Builder $w) use ($user, $groupIds) {
                $w->where(fn ($q) => $q->where('tasks.is_personal', true)->where('tasks.created_by', $user->id))
                    ->orWhereExists(fn (QueryBuilder $q) => $q->from('task_assignees as ta')
                        ->whereColumn('ta.task_id', 'tasks.id')
                        ->where(fn (QueryBuilder $a) => $a->where('ta.user_id', $user->id)
                            ->when($groupIds, fn ($g) => $g->orWhereIn('ta.group_id', $groupIds))
                            ->orWhereExists(fn (QueryBuilder $r) => $r->from('model_has_roles as mhr')
                                ->join('roles', 'roles.id', '=', 'mhr.role_id')
                                ->where('mhr.model_type', 'user')
                                ->where('mhr.model_id', $user->id)
                                ->whereColumn('mhr.school_id', 'tasks.school_id')
                                ->whereColumn('roles.name', 'ta.role'))));
            });
    }

    /** Tasks this person created for someone else. */
    public function scopeAssignedBy(Builder $query, User $user): void
    {
        $query->where('tasks.created_by', $user->id)->where('tasks.is_personal', false);
    }

    /**
     * Active people the task is for: the author of a personal task, else
     * everyone reached by the assignees (or only by the given keys).
     *
     * @param  list<string>|null  $keys
     * @return Collection<int, int>
     */
    public function recipientIds(?array $keys = null): Collection
    {
        if ($this->is_personal) {
            return collect([$this->created_by])->filter()->values();
        }

        $keys ??= $this->assignees()->get()->map->key()->all();

        return $keys ? Audience::fromKeys($keys)->resolve($this->school) : collect();
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    /** Due at, for display: date only for all-day tasks. */
    public function dueLabel(): ?string
    {
        if (! $this->due_at) {
            return null;
        }

        $date = match (true) {
            $this->due_at->isToday() => __('dziś'),
            $this->due_at->isTomorrow() => __('jutro'),
            $this->due_at->isYesterday() => __('wczoraj'),
            default => $this->due_at->translatedFormat($this->due_at->year === now()->year ? 'j M' : 'j M Y'),
        };

        return $this->all_day ? $date : $date.', '.$this->due_at->format('H:i');
    }

    /** @return array{done: int, total: int} */
    public function checklistProgress(): array
    {
        $items = $this->relationLoaded('checklist') ? $this->checklist : $this->checklist()->get();

        return ['done' => $items->where('is_done', true)->count(), 'total' => $items->count()];
    }
}
