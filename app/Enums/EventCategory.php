<?php

namespace App\Enums;

enum EventCategory: string
{
    case OpenDay = 'open_day';
    case Competition = 'competition';
    case Recruitment = 'recruitment';
    case Ceremony = 'ceremony';
    case Sport = 'sport';
    case Charity = 'charity';
    case Meeting = 'meeting';
    case Other = 'other';

    public function label(): string
    {
        return __("enums.event_category.{$this->value}");
    }

    public function icon(): string
    {
        return match ($this) {
            self::OpenDay => 'building-library',
            self::Competition => 'trophy',
            self::Recruitment => 'academic-cap',
            self::Ceremony => 'sparkles',
            self::Sport => 'flag',
            self::Charity => 'heart',
            self::Meeting => 'users',
            self::Other => 'calendar',
        };
    }
}
