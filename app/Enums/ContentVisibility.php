<?php

namespace App\Enums;

/**
 * Who may see an event (docs/ARCHITECTURE.md §3.0.3).
 */
enum ContentVisibility: string
{
    case Public = 'public';
    case InterSchool = 'inter_school';
    case SchoolOnly = 'school_only';
    case PrivateGroup = 'private_group';

    public function label(): string
    {
        return __("enums.visibility.{$this->value}");
    }

    public function description(): string
    {
        return __("enums.visibility_help.{$this->value}");
    }

    public function icon(): string
    {
        return match ($this) {
            self::Public => 'globe-alt',
            self::InterSchool => 'building-office-2',
            self::SchoolOnly => 'building-library',
            self::PrivateGroup => 'lock-closed',
        };
    }

    /**
     * Levels selectable in Step 3. private_group needs event audiences and
     * members, which arrive with the Project Manager step.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::SchoolOnly, self::InterSchool, self::Public];
    }
}
