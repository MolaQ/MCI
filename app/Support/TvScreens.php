<?php

namespace App\Support;

use App\Models\School;

/**
 * Which screens the TV board shows, in which order and for how long.
 * Resolution: config defaults → site (Witryna → Tryb TV) → school
 * (Szkoła → Tryb TV, when it has its own) → the board's own URL
 * (?ekrany=agenda:20,calendar:30).
 */
class TvScreens
{
    public const KEYS = ['agenda', 'calendar', 'projects', 'announcements'];

    public function __construct(private readonly Settings $settings) {}

    /** @return array<string, array{label: string, icon: string, hint: string}> */
    public static function catalog(): array
    {
        return [
            'agenda' => ['label' => __('Agenda dnia'), 'icon' => 'sun', 'hint' => __('Co dziś dzieje się w szkole: wydarzenia i najważniejsze komunikaty.')],
            'calendar' => ['label' => __('Kalendarz miesiąca'), 'icon' => 'calendar-days', 'hint' => __('Siatka miesiąca z wydarzeniami i kamieniami milowymi.')],
            'projects' => ['label' => __('Projekty w czasie'), 'icon' => 'rectangle-stack', 'hint' => __('Trwające projekty publiczne jako paski na osi czasu.')],
            'announcements' => ['label' => __('Komunikaty'), 'icon' => 'megaphone', 'hint' => __('Ostatnie komunikaty publiczne, pilne wyróżnione.')],
        ];
    }

    /** @return list<array{key: string, on: bool, seconds: int}> */
    public static function defaults(): array
    {
        $out = [];
        foreach ((array) config('mci.tv.screens') as $key => $seconds) {
            if (in_array($key, self::KEYS, true)) {
                $out[] = ['key' => $key, 'on' => true, 'seconds' => self::clamp((int) $seconds)];
            }
        }

        return self::normalize($out);
    }

    public static function clamp(int $seconds): int
    {
        return max((int) config('mci.tv.min_seconds', 5), min((int) config('mci.tv.max_seconds', 600), $seconds));
    }

    /**
     * Cleans stored or submitted rows: known keys only, each once, every
     * screen present (missing ones appended switched off), sane seconds.
     *
     * @return list<array{key: string, on: bool, seconds: int}>
     */
    public static function normalize(mixed $rows, ?array $fallback = null): array
    {
        $fallback ??= array_map(fn ($k) => ['key' => $k, 'on' => true, 'seconds' => 20], self::KEYS);
        $byKey = collect($fallback)->keyBy('key');
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;
            if (! in_array($key, self::KEYS, true) || isset($out[$key])) {
                continue;
            }
            $out[$key] = ['key' => $key, 'on' => (bool) ($row['on'] ?? true), 'seconds' => self::clamp((int) ($row['seconds'] ?? $byKey[$key]['seconds'] ?? 20))];
        }
        foreach (self::KEYS as $key) {
            $out[$key] ??= ['key' => $key, 'on' => false, 'seconds' => (int) ($byKey[$key]['seconds'] ?? 20)];
        }

        return array_values($out);
    }

    /** @return list<array{key: string, on: bool, seconds: int}> */
    public function site(): array
    {
        $stored = $this->settings->get('tv.screens');

        return $stored ? self::normalize($stored, self::defaults()) : self::defaults();
    }

    /** @return list<array{key: string, on: bool, seconds: int}>|null  null = follows the site */
    public function ownOf(School $school): ?array
    {
        $stored = $school->setting('tv.screens');

        return $stored ? self::normalize($stored, $this->site()) : null;
    }

    /** @return list<array{key: string, on: bool, seconds: int}> */
    public function forSchool(?School $school): array
    {
        return ($school ? $this->ownOf($school) : null) ?? $this->site();
    }

    /**
     * The screens one board shows: enabled ones of the school/site, or the
     * list from its URL ("agenda:20,calendar" — seconds optional).
     *
     * @return list<array{key: string, seconds: int}>
     */
    public function resolve(?School $school, ?string $override = null): array
    {
        $base = $this->forSchool($school);
        $rows = $base;
        if ($override !== null && trim($override) !== '') {
            $seconds = collect($base)->pluck('seconds', 'key');
            $rows = [];
            foreach (array_slice(explode(',', $override), 0, 10) as $part) {
                [$key, $s] = array_pad(explode(':', trim($part), 2), 2, null);
                if (in_array($key, self::KEYS, true)) {
                    $rows[] = ['key' => $key, 'on' => true, 'seconds' => $s !== null && ctype_digit($s) ? self::clamp((int) $s) : (int) $seconds[$key]];
                }
            }
            $rows = $rows ? collect($rows)->unique('key')->values()->all() : $base;
        }

        $shown = array_values(array_map(fn ($r) => ['key' => $r['key'], 'seconds' => $r['seconds']], array_filter($rows, fn ($r) => $r['on'])));

        return $shown ?: [['key' => 'agenda', 'seconds' => 30]];
    }

    /** "agenda:20,calendar:30" for building a board link. */
    public static function toQuery(array $rows): string
    {
        return collect($rows)->filter(fn ($r) => $r['on'] ?? true)->map(fn ($r) => $r['key'].':'.$r['seconds'])->implode(',');
    }
}
