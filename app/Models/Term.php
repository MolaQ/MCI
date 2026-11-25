<?php

namespace App\Models;

use App\Enums\TermKind;
use App\Support\Appearance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry of an editable dictionary: an event category or a group type.
 * school_id = null means the entry belongs to the whole portal.
 *
 * Views call label() / icon() / color(), the same API the old enums had.
 *
 * @property int $id
 * @property TermKind $kind
 * @property ?int $school_id
 * @property string $key
 * @property string $name
 * @property string $icon
 * @property ?string $color
 * @property int $position
 * @property bool $is_active
 * @property bool $is_featured
 */
class Term extends Model
{
    /** Columns that are safe on public pages (no author, no timestamps). */
    public const PUBLIC_COLUMNS = ['id', 'kind', 'school_id', 'key', 'name', 'icon', 'is_featured'];

    protected $fillable = ['kind', 'school_id', 'key', 'name', 'icon', 'color', 'position', 'is_active', 'is_featured', 'created_by'];

    protected function casts(): array
    {
        return [
            'kind' => TermKind::class,
            'position' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'type_id');
    }

    public function scopeOfKind(Builder $query, TermKind $kind): void
    {
        $query->where('terms.kind', $kind->value);
    }

    public function scopeOrdered(Builder $query): void
    {
        // Portal-wide entries first, then the school's own, each in their own order.
        $query->orderByRaw('case when terms.school_id is null then 0 else 1 end')
            ->orderBy('terms.position')->orderBy('terms.id');
    }

    /** Id of the portal-wide term with this key (seeders, tests, fallbacks). */
    public static function idFor(TermKind $kind, string $key): ?int
    {
        return static::query()->ofKind($kind)->whereNull('school_id')->where('key', $key)->value('id');
    }

    public function isGlobal(): bool
    {
        return $this->school_id === null;
    }

    public function label(): string
    {
        return $this->name;
    }

    public function icon(): string
    {
        return in_array($this->icon, TermKind::ICONS, true) ? $this->icon : ($this->kind?->defaultIcon() ?? 'tag');
    }

    /** Chip colour for group types (sanitised again on output). */
    public function color(): string
    {
        return Appearance::sanitizeHex($this->color, '#475569');
    }

    /** How many events or groups point at this entry (deleting is allowed only at 0). */
    public function usageCount(): int
    {
        return $this->kind === TermKind::EventCategory
            ? Event::withTrashed()->where('category_id', $this->id)->count()
            : Group::withTrashed()->where('type_id', $this->id)->count();
    }
}
