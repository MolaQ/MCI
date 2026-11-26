<?php

namespace App\Domain\Calendar;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * One entry of the agenda / month calendar: an event, a task deadline, a
 * project milestone or a decision deadline. Times are in Europe/Warsaw.
 */
final class CalendarItem
{
    /** Tailwind classes per tone (kept literal for the class scanner). */
    public const TONES = [
        'primary' => ['dot' => 'bg-primary', 'chip' => 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-white', 'bar' => 'bg-primary'],
        'sky' => ['dot' => 'bg-sky-500', 'chip' => 'bg-sky-50 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200', 'bar' => 'bg-sky-500'],
        'violet' => ['dot' => 'bg-violet-500', 'chip' => 'bg-violet-50 text-violet-800 dark:bg-violet-500/15 dark:text-violet-200', 'bar' => 'bg-violet-500'],
        'amber' => ['dot' => 'bg-amber-500', 'chip' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200', 'bar' => 'bg-amber-500'],
        'red' => ['dot' => 'bg-red-500', 'chip' => 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-200', 'bar' => 'bg-red-500'],
        'emerald' => ['dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200', 'bar' => 'bg-emerald-500'],
    ];

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly CarbonImmutable $start,
        public readonly ?CarbonImmutable $end = null,
        public readonly bool $allDay = false,
        public readonly ?string $url = null,
        public readonly string $icon = 'calendar',
        public readonly string $tone = 'primary',
        public readonly ?string $meta = null,
        public readonly bool $done = false,
        public readonly bool $alert = false,
    ) {}

    public function tone(string $part = 'dot'): string
    {
        return self::TONES[$this->tone][$part] ?? self::TONES['primary'][$part];
    }

    /** Running right now (for the "Teraz" mark in today's agenda). */
    public function isNow(): bool
    {
        return $this->kind === 'event' && ! $this->allDay && $this->start->isPast() && $this->end && $this->end->isFuture();
    }

    /** Already over today (dimmed in the agenda). */
    public function isOver(): bool
    {
        return $this->kind === 'event' && ! $this->allDay && ($this->end ?? $this->start->addHour())->isPast();
    }

    public function key(): string
    {
        return $this->kind.'-'.md5($this->title.$this->start->toIso8601String().$this->url);
    }

    public function lastDay(): CarbonImmutable
    {
        return ($this->end && $this->end->greaterThan($this->start) ? $this->end : $this->start)->startOfDay();
    }

    public function covers(CarbonInterface $day): bool
    {
        $d = $day->toImmutable()->startOfDay();

        return $d->betweenIncluded($this->start->startOfDay(), $this->lastDay());
    }

    public function isMultiDay(): bool
    {
        return ! $this->lastDay()->isSameDay($this->start);
    }

    /** "08:00", "08:00–10:30", "cały dzień" or "do 15:00" — as seen on $day. */
    public function timeLabel(?CarbonInterface $day = null): string
    {
        $day ??= $this->start;
        if ($this->allDay && in_array($this->kind, ['task', 'milestone', 'decision'], true)) {
            return __('do końca dnia');
        }
        if ($this->allDay || ($this->isMultiDay() && ! $this->start->isSameDay($day) && ! $this->lastDay()->isSameDay($day))) {
            return __('cały dzień');
        }
        if (in_array($this->kind, ['task', 'milestone', 'decision'], true)) {
            return __('do :time', ['time' => $this->start->format('H:i')]);
        }
        if ($this->isMultiDay() && ! $this->start->isSameDay($day)) {
            return __('do :time', ['time' => $this->end->format('H:i')]);
        }

        return $this->start->format('H:i').($this->end && $this->end->isSameDay($this->start) ? '–'.$this->end->format('H:i') : '');
    }

    /** Sort key within a day: all-day first, then by time. */
    public function sortKey(CarbonInterface $day): string
    {
        $allDay = $this->allDay || ($this->isMultiDay() && ! $this->start->isSameDay($day));

        return ($allDay ? '0' : '1').$this->start->format('Hi').$this->title;
    }
}
