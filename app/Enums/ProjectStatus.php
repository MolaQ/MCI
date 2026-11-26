<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("enums.project_status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'zinc',
            self::InProgress => 'sky',
            self::OnHold => 'amber',
            self::Completed => 'emerald',
            self::Cancelled => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Planned => 'calendar',
            self::InProgress => 'play-circle',
            self::OnHold => 'pause-circle',
            self::Completed => 'check-badge',
            self::Cancelled => 'x-circle',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Planned, self::InProgress, self::OnHold], true);
    }
}
