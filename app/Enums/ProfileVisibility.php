<?php

namespace App\Enums;

/**
 * How far outside their own schools a person's profile is visible.
 * Ordered from most private to most open; the effective level is the
 * stricter of the person's choice and every school policy cap.
 */
enum ProfileVisibility: string
{
    case School = 'school';
    case Network = 'network';
    case Public = 'public';

    public function rank(): int
    {
        return match ($this) {
            self::School => 0,
            self::Network => 1,
            self::Public => 2,
        };
    }

    public static function strictest(self ...$levels): self
    {
        return collect($levels)->sortBy(fn (self $l) => $l->rank())->first() ?? self::School;
    }

    public function label(): string
    {
        return __("enums.profile_visibility.{$this->value}");
    }

    public function description(): string
    {
        return __("enums.profile_visibility_help.{$this->value}");
    }
}
