<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** One entry of a request's history: a submission or a decision, with an optional comment and images. */
class WorkflowAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['request_id', 'user_id', 'stage_position', 'action', 'comment'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(WorkflowRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'owner')->where('collection', 'image')->orderBy('position');
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'source');
    }

    public function label(): string
    {
        return __('enums.workflow_action.'.$this->action);
    }
}
