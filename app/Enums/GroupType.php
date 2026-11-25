<?php

namespace App\Enums;

enum GroupType: string
{
    case Staff = 'staff';
    case Subject = 'subject';
    case Vocational = 'vocational';
    case ClassTutors = 'class_tutors';
    case SchoolClass = 'class';
    case Volunteering = 'volunteering';
    case Sport = 'sport';
    case Custom = 'custom';

    public function label(): string
    {
        return __("enums.group_type.{$this->value}");
    }

    /**
     * Default chip colour (hex) when a group has no colour of its own.
     */
    public function color(): string
    {
        return match ($this) {
            self::Staff => '#71717a',
            self::Subject => '#2563eb',
            self::Vocational => '#ea580c',
            self::ClassTutors => '#7c3aed',
            self::SchoolClass => '#0891b2',
            self::Volunteering => '#db2777',
            self::Sport => '#16a34a',
            self::Custom => '#475569',
        };
    }
}
