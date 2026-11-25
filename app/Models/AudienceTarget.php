<?php

namespace App\Models;

use App\Enums\AudienceTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AudienceTarget extends Model
{
    public $timestamps = false;

    protected $fillable = ['target_type', 'target_id', 'include_subgroups'];

    protected function casts(): array
    {
        return ['target_type' => AudienceTargetType::class, 'include_subgroups' => 'boolean'];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
