<?php

namespace App\Models;

use App\Enums\Feature;
use App\Support\Features;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable approval procedure: ordered stages plus the fields a request
 * must fill in. school_id = null makes it a system-wide template that every
 * school sees (when "workflows.system_templates" is on).
 *
 * @property list<array{key: string, label: string, type: string, required: bool, options?: list<string>}>|null $fields
 */
class WorkflowTemplate extends Model
{
    use SoftDeletes;

    public const FIELD_TYPES = ['text', 'textarea', 'date', 'number', 'select', 'checkbox'];

    protected $fillable = ['school_id', 'name', 'description', 'icon', 'fields', 'min_attachments', 'is_active', 'position', 'created_by'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'is_active' => 'boolean', 'min_attachments' => 'integer'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class, 'template_id')->orderBy('position')->orderBy('id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(WorkflowRequest::class, 'template_id');
    }

    public function isSystem(): bool
    {
        return $this->school_id === null;
    }

    /** Active templates a member of $school may submit: the school's own, plus system-wide ones where allowed. */
    public function scopeAvailableIn(Builder $query, School $school): void
    {
        $system = app(Features::class)->enabled(Feature::WorkflowSystemTemplates, $school);

        $query->where('workflow_templates.is_active', true)
            ->where(fn ($q) => $q->where('workflow_templates.school_id', $school->id)
                ->when($system, fn ($s) => $s->orWhereNull('workflow_templates.school_id')))
            ->whereHas('stages');
    }
}
