<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("enums.task_status.{$this->value}");
    }

    /** Flux badge colour. */
    public function color(): string
    {
        return match ($this) {
            self::Todo => 'zinc',
            self::InProgress => 'sky',
            self::Done => 'emerald',
            self::Cancelled => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Todo => 'stop',
            self::InProgress => 'play',
            self::Done => 'check-circle',
            self::Cancelled => 'x-circle',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Todo || $this === self::InProgress;
    }

    /** One-click cycle on the list: do zrobienia → w trakcie → gotowe → do zrobienia. */
    public function next(): self
    {
        return match ($this) {
            self::Todo => self::InProgress,
            self::InProgress => self::Done,
            self::Done, self::Cancelled => self::Todo,
        };
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Todo->value, self::InProgress->value];
    }
}
