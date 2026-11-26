<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case InProgress = 'in_progress';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("enums.workflow_status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::InProgress => 'sky',
            self::Returned => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'red',
            self::Cancelled => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InProgress => 'arrow-path',
            self::Returned => 'arrow-uturn-left',
            self::Approved => 'check-badge',
            self::Rejected => 'x-circle',
            self::Cancelled => 'minus-circle',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::InProgress || $this === self::Returned;
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::InProgress->value, self::Returned->value];
    }
}
