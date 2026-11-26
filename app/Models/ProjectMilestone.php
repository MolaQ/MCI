<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stage / milestone of a project with an optional deadline (Europe/Warsaw).
 */
class ProjectMilestone extends Model
{
    protected $fillable = ['project_id', 'title', 'description', 'due_at', 'completed_at', 'completed_by', 'position', 'reminded_at', 'overdue_notified_at'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_milestone_id');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_at !== null && $this->due_at->isPast();
    }
}
