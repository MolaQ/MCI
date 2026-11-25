<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One assignment of a task: a person, a group (team) or a role — exactly one.
 */
class TaskAssignee extends Model
{
    public $timestamps = false;

    protected $fillable = ['task_id', 'user_id', 'group_id', 'role', 'assigned_by', 'assigned_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** Compact key, as used by the form: user:1, group:2, role:teacher. */
    public function key(): string
    {
        return match (true) {
            $this->user_id !== null => 'user:'.$this->user_id,
            $this->group_id !== null => 'group:'.$this->group_id,
            default => 'role:'.$this->role,
        };
    }

    public function label(): string
    {
        return match (true) {
            $this->user_id !== null => $this->user?->name ?? __('(usunięta osoba)'),
            $this->group_id !== null => $this->group?->name ?? __('(usunięta grupa)'),
            default => Role::tryFrom((string) $this->role)?->label() ?? (string) $this->role,
        };
    }

    public function icon(): string
    {
        return match (true) {
            $this->user_id !== null => 'user',
            $this->group_id !== null => 'user-group',
            default => 'identification',
        };
    }
}
