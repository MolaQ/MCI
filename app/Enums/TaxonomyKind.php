<?php

namespace App\Enums;

use App\Models\Event;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;

/**
 * Editable lists (docs/ARCHITECTURE.md §3.8): event categories and group
 * types. Items live in `taxonomy_terms`; records store the term's key.
 */
enum TaxonomyKind: string
{
    case EventCategory = 'event_category';
    case GroupType = 'group_type';

    public function label(): string
    {
        return match ($this) {
            self::EventCategory => __('Kategorie wydarzeń'),
            self::GroupType => __('Typy grup'),
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::EventCategory => __('kategoria wydarzeń'),
            self::GroupType => __('typ grupy'),
        };
    }

    public function defaultIcon(): string
    {
        return $this === self::EventCategory ? 'calendar' : 'user-group';
    }

    /** Whether items of this kind carry a colour (group chips do, event categories stay monochrome). */
    public function hasColor(): bool
    {
        return $this === self::GroupType;
    }

    /** The feature switch that lets schools keep their own list. */
    public function schoolFeature(): Feature
    {
        return $this === self::EventCategory ? Feature::SchoolEventCategories : Feature::SchoolGroupTypes;
    }

    /** Records that use a term (column holding the key). */
    public function usage(): Builder
    {
        return match ($this) {
            self::EventCategory => Event::query()->withTrashed(),
            self::GroupType => Group::query()->withTrashed(),
        };
    }

    public function column(): string
    {
        return $this === self::EventCategory ? 'category' : 'type';
    }

    /** Built-in terms copied into `taxonomy_terms` on install: key => [icon, colour]. */
    public function builtIn(): array
    {
        return match ($this) {
            self::EventCategory => collect(EventCategory::cases())->mapWithKeys(fn (EventCategory $c) => [$c->value => [$c->icon(), null]])->all(),
            self::GroupType => collect(GroupType::cases())->mapWithKeys(fn (GroupType $t) => [$t->value => [$t->icon(), $t->color()]])->all(),
        };
    }
}
