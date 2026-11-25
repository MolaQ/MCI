<?php

namespace App\Support;

use App\Enums\TaxonomyKind;
use App\Models\School;
use App\Models\TaxonomyTerm;
use Illuminate\Support\Collection;

/**
 * Resolves event categories and group types for a school: the site-wide list
 * (minus terms the school hid) plus the school's own terms, when the site
 * lets schools keep their own (feature switch per kind). Loaded once per
 * request; the tables are tiny.
 */
class Taxonomy
{
    /** @var array<string, Collection<int, TaxonomyTerm>> */
    private array $all = [];

    public function __construct(private Features $features) {}

    /** @return Collection<int, TaxonomyTerm> */
    public function all(TaxonomyKind $kind): Collection
    {
        return $this->all[$kind->value] ??= TaxonomyTerm::query()->ofKind($kind)
            ->orderByRaw('school_id is not null')->orderBy('position')->orderBy('id')->get();
    }

    public function forget(): void
    {
        $this->all = [];
    }

    /** The term behind a stored key: the school's own first, then the site-wide one. */
    public function resolve(TaxonomyKind $kind, ?string $key, ?int $schoolId = null): TaxonomyTerm
    {
        $key = (string) $key;
        $terms = $this->all($kind)->where('key', $key);

        return ($schoolId ? $terms->firstWhere('school_id', $schoolId) : null)
            ?? $terms->firstWhere('school_id', null)
            ?? TaxonomyTerm::placeholder($kind, $key);
    }

    /** Whether the school may keep its own list and hide site-wide terms. */
    public function schoolMayCustomise(TaxonomyKind $kind, ?School $school): bool
    {
        return $school !== null && $this->features->enabled($kind->schoolFeature(), $school);
    }

    /** @return list<string> */
    public function hiddenKeys(TaxonomyKind $kind, ?School $school): array
    {
        if (! $this->schoolMayCustomise($kind, $school)) {
            return [];
        }

        return array_values((array) data_get($school->settings ?? [], 'taxonomy_hidden.'.$kind->value, []));
    }

    public function setHidden(TaxonomyKind $kind, School $school, string $key, bool $hidden): void
    {
        $settings = $school->settings ?? [];
        $keys = collect(data_get($settings, 'taxonomy_hidden.'.$kind->value, []));
        $keys = $hidden ? $keys->push($key)->unique() : $keys->reject(fn ($k) => $k === $key);
        data_set($settings, 'taxonomy_hidden.'.$kind->value, $keys->values()->all());
        $school->forceFill(['settings' => $settings])->save();
    }

    /**
     * Terms offered when choosing (forms, filters): active site-wide terms the
     * school did not hide, then the school's own active terms. $keep stays in
     * the list even if archived, so editing an old record never loses its value.
     *
     * @return Collection<int, TaxonomyTerm>
     */
    public function options(TaxonomyKind $kind, ?School $school, ?string $keep = null): Collection
    {
        $hidden = $this->hiddenKeys($kind, $school);
        $own = $this->schoolMayCustomise($kind, $school);

        $terms = $this->all($kind)->filter(fn (TaxonomyTerm $t) => $t->is_active && (
            ($t->school_id === null && ! in_array($t->key, $hidden, true))
            || ($own && $t->school_id === $school?->id)
        ));

        if ($keep !== null && $keep !== '' && ! $terms->contains('key', $keep)) {
            $current = $this->resolve($kind, $keep, $school?->id);
            if ($current->exists) {
                $terms->push($current);
            }
        }

        // A school term with the same key as a site-wide one replaces it.
        return $terms->reverse()->unique('key')->reverse()->values();
    }

    /** @return list<string> */
    public function keys(TaxonomyKind $kind, ?School $school, ?string $keep = null): array
    {
        return $this->options($kind, $school, $keep)->pluck('key')->all();
    }
}
