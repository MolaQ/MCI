<?php

namespace App\Enums;

enum TaskPriority: int
{
    case Low = 1;
    case Normal = 2;
    case High = 3;
    case Urgent = 4;

    public function label(): string
    {
        return __("enums.task_priority.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'zinc',
            self::Normal => 'sky',
            self::High => 'amber',
            self::Urgent => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => 'chevron-double-down',
            self::Normal => 'minus',
            self::High => 'chevron-up',
            self::Urgent => 'bolt',
        };
    }
}
