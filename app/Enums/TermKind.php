<?php

namespace App\Enums;

/**
 * Editable dictionaries (table `terms`). Each kind has portal-wide entries
 * (school_id = null, managed by site admins) and, when the school's feature
 * switch allows it, a school's own entries (categories.manage).
 */
enum TermKind: string
{
    case EventCategory = 'event_category';
    case GroupType = 'group_type';

    /**
     * Monochrome Heroicons (currentColor) that may be chosen for a term.
     */
    public const ICONS = [
        'calendar', 'calendar-days', 'building-library', 'building-office-2', 'trophy', 'academic-cap',
        'sparkles', 'flag', 'heart', 'users', 'user-group', 'megaphone', 'musical-note', 'book-open',
        'beaker', 'cpu-chip', 'wrench-screwdriver', 'globe-europe-africa', 'map', 'camera', 'film',
        'paint-brush', 'puzzle-piece', 'light-bulb', 'briefcase', 'chat-bubble-left-right',
        'presentation-chart-bar', 'star', 'gift', 'shield-check', 'bolt', 'rocket-launch',
        'computer-desktop', 'language', 'scale', 'hand-raised', 'sun', 'truck', 'clipboard-document-list',
        'identification', 'newspaper', 'tag',
    ];

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
            self::EventCategory => __('kategoria'),
            self::GroupType => __('typ grupy'),
        };
    }

    /** The switch that lets a school add its own entries and hide portal-wide ones. */
    public function feature(): Feature
    {
        return match ($this) {
            self::EventCategory => Feature::EventCategoriesCustom,
            self::GroupType => Feature::GroupTypesCustom,
        };
    }

    /** Key of the portal-wide entry used when nothing else is chosen. */
    public function fallbackKey(): string
    {
        return match ($this) {
            self::EventCategory => 'other',
            self::GroupType => 'custom',
        };
    }

    public function defaultIcon(): string
    {
        return match ($this) {
            self::EventCategory => 'calendar',
            self::GroupType => 'user-group',
        };
    }

    /** Group types colour the group chips; event categories stay monochrome. */
    public function usesColor(): bool
    {
        return $this === self::GroupType;
    }

    /** Event categories may be highlighted on the public front. */
    public function usesFeatured(): bool
    {
        return $this === self::EventCategory;
    }
}
