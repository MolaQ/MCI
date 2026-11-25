<?php

namespace App\Models;

use App\Enums\SchoolOfferKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolOffer extends Model
{
    protected $fillable = ['school_id', 'kind', 'name', 'description', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['kind' => SchoolOfferKind::class, 'is_active' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
