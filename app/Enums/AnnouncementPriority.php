<?php

namespace App\Enums;

enum AnnouncementPriority: string
{
    case Normal = 'normal';
    case Important = 'important';
    case Urgent = 'urgent';

    public function label(): string
    {
        return __("enums.announcement_priority.{$this->value}");
    }

    /** Flux badge colour. */
    public function color(): string
    {
        return match ($this) {
            self::Normal => 'zinc',
            self::Important => 'amber',
            self::Urgent => 'red',
        };
    }

    /** Monochrome Heroicon name (rendered with currentColor). */
    public function icon(): string
    {
        return match ($this) {
            self::Normal => 'chat-bubble-left',
            self::Important => 'exclamation-circle',
            self::Urgent => 'bolt',
        };
    }

    /** Important and urgent posts also land in the notification bell. */
    public function notifiesInBell(): bool
    {
        return $this !== self::Normal;
    }
}
