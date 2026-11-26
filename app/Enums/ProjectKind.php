<?php

namespace App\Enums;

enum ProjectKind: string
{
    case Event = 'event';
    case OpenDay = 'open_day';
    case Competition = 'competition';
    case Grant = 'grant';
    case Erasmus = 'erasmus';
    case Investment = 'investment';
    case Other = 'other';

    public function label(): string
    {
        return __("enums.project_kind.{$this->value}");
    }

    public function icon(): string
    {
        return match ($this) {
            self::Event => 'calendar-days',
            self::OpenDay => 'building-library',
            self::Competition => 'trophy',
            self::Grant => 'banknotes',
            self::Erasmus => 'globe-europe-africa',
            self::Investment => 'wrench-screwdriver',
            self::Other => 'squares-2x2',
        };
    }
}
