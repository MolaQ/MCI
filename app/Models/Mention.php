<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * "@user" or "@group" inside any rich text (announcements now; tasks,
 * comments and events later). See App\Support\Mentions.
 */
class Mention extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['mentionable_type', 'mentionable_id', 'school_id', 'created_by'];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function mentionable(): MorphTo
    {
        return $this->morphTo();
    }
}
