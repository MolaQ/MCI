<?php

namespace App\Models;

use App\Enums\TaxonomyKind;
use App\Support\Appearance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One editable event category or group type (school_id null = site-wide).
 * Exposes label() / icon() / color() so views treat it like the old enums.
 *
 * @property int $id
 * @property TaxonomyKind $kind
 * @property ?int $school_id
 * @property string $key
 * @property string $name
 * @property string $icon
 * @property ?string $color
 * @property int $position
 * @property bool $is_active
 * @property bool $is_featured
 */
class TaxonomyTerm extends Model
{
    protected $fillable = ['kind', 'school_id', 'key', 'name', 'icon', 'color', 'position', 'is_active', 'is_featured'];

    protected function casts(): array
    {
        return [
            'kind' => TaxonomyKind::class,
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function scopeOfKind(Builder $query, TaxonomyKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    public function label(): string
    {
        return $this->name;
    }

    public function icon(): string
    {
        return $this->icon ?: $this->kind->defaultIcon();
    }

    public function color(): string
    {
        return Appearance::sanitizeHex($this->color, '#71717a');
    }

    /** The site-wide catch-all ("Inne"): always offered, so every list keeps an option. */
    public function isFallback(): bool
    {
        return $this->school_id === null && $this->key === $this->kind->fallbackKey();
    }

    public function isGlobal(): bool
    {
        return $this->school_id === null;
    }

    /** Stand-in for a key that has no term (e.g. a term deleted by hand in the database). */
    public static function placeholder(TaxonomyKind $kind, string $key): self
    {
        $term = new self(['kind' => $kind, 'key' => $key, 'name' => $key === '' ? __('Bez kategorii') : $key, 'icon' => $kind->defaultIcon(), 'is_active' => false]);
        $term->exists = false;

        return $term;
    }
}
