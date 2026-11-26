<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a project team: a person, a group or a role (in `school_id`).
 */
class ProjectMember extends Model
{
    protected $fillable = ['project_id', 'school_id', 'user_id', 'group_id', 'role'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** Form key: user:1, group:2, role:teacher (a role is read in the project's school). */
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
