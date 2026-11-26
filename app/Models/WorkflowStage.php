<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a template: who decides (a role, a group or a named person,
 * in the requester's school or — cross-school — in a given one), how long
 * they have (SLA in hours) and which To-Do tasks it creates.
 */
class WorkflowStage extends Model
{
    protected $fillable = ['template_id', 'position', 'name', 'approver_type', 'approver_id', 'school_id', 'sla_hours', 'task_on_reach', 'followup_task'];

    protected function casts(): array
    {
        return ['task_on_reach' => 'boolean', 'sla_hours' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'template_id');
    }

    /** The frozen copy stored on a request when it is submitted. */
    public function snapshot(): array
    {
        return [
            'name' => $this->name,
            'approver_type' => $this->approver_type,
            'approver_id' => (string) $this->approver_id,
            'school_id' => $this->school_id,
            'sla_hours' => $this->sla_hours,
            'task_on_reach' => $this->task_on_reach,
            'followup_task' => $this->followup_task,
            'approver_label' => self::approverLabel($this->approver_type, (string) $this->approver_id),
        ];
    }

    public static function approverLabel(string $type, string $id): string
    {
        return match ($type) {
            'role' => Role::tryFrom($id)?->label() ?? $id,
            'group' => Group::query()->find((int) $id)?->name ?? __('(usunięta grupa)'),
            'user' => User::query()->find((int) $id)?->name ?? __('(usunięta osoba)'),
            default => $id,
        };
    }
}
