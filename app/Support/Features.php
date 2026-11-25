<?php

namespace App\Support;

use App\Enums\Feature;
use App\Models\School;
use Illuminate\Support\Collection;

/**
 * Resolves feature switches (App\Enums\Feature) at two levels.
 *
 *  site   settings `features` = { key: { on: bool, override: bool } }
 *  school schools.settings.features = { key: bool }
 *
 * Effective for a school = parent effective AND site on AND
 * (override not allowed OR school value ?? true). With no school (public
 * pages without a school, CLI) only the site level applies.
 */
class Features
{
    /** @var array<string, bool> */
    private array $memo = [];

    /** @var array<string, Collection<int, int>> */
    private array $without = [];

    public function __construct(private Settings $settings, private CurrentSchool $current) {}

    /**
     * @return array{on: bool, override: bool}
     */
    public function site(Feature $feature): array
    {
        $stored = (array) (($this->settings->get('features') ?? [])[$feature->value] ?? []);

        return [
            'on' => (bool) ($stored['on'] ?? true),
            'override' => (bool) ($stored['override'] ?? $feature->schoolOverridableByDefault()),
        ];
    }

    public function schoolValue(School $school, Feature $feature): ?bool
    {
        $value = (($school->settings ?? [])['features'] ?? [])[$feature->value] ?? null;

        return $value === null ? null : (bool) $value;
    }

    /**
     * @param  School|int|false|null  $school  false = current school, null = site level only
     */
    public function enabled(Feature $feature, School|int|false|null $school = false): bool
    {
        $school = $school === false ? $this->current->get() : $school;
        if (is_int($school)) {
            $school = School::query()->find($school);
        }

        $key = $feature->value.'@'.($school?->id ?? 'site').'#'.($school?->updated_at?->timestamp ?? 0);

        return $this->memo[$key] ??= $this->resolve($feature, $school);
    }

    public function disabled(Feature $feature, School|int|false|null $school = false): bool
    {
        return ! $this->enabled($feature, $school);
    }

    /**
     * Ids of active schools where the feature is off (used to filter public lists).
     *
     * @return Collection<int, int>
     */
    public function schoolsWithout(Feature $feature): Collection
    {
        return $this->without[$feature->value] ??= School::query()->get(['id', 'settings', 'updated_at'])
            ->reject(fn (School $s) => $this->enabled($feature, $s))
            ->pluck('id')->values();
    }

    public function setSite(Feature $feature, bool $on, bool $override): void
    {
        $all = (array) ($this->settings->get('features') ?? []);
        $all[$feature->value] = ['on' => $on, 'override' => $override];
        $this->settings->set('features', $all);
        $this->memo = [];
        $this->without = [];
    }

    public function setForSchool(School $school, Feature $feature, ?bool $value): void
    {
        $settings = $school->settings ?? [];
        $features = $settings['features'] ?? [];
        if ($value === null) {
            unset($features[$feature->value]);
        } else {
            $features[$feature->value] = $value;
        }
        $settings['features'] = $features;
        $school->forceFill(['settings' => $settings])->save();
        $this->memo = [];
        $this->without = [];
    }

    private function resolve(Feature $feature, ?School $school): bool
    {
        if ($feature->parent() && ! $this->enabled($feature->parent(), $school ?? null)) {
            return false;
        }

        $site = $this->site($feature);
        if (! $site['on']) {
            return false;
        }
        if (! $school || ! $site['override']) {
            return true;
        }

        return $this->schoolValue($school, $feature) ?? true;
    }
}
