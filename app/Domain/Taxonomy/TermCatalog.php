<?php

namespace App\Domain\Taxonomy;

use App\Enums\TermKind;
use App\Models\School;
use App\Models\Term;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which event categories / group types a school may use.
 *
 * Portal-wide entries (school_id = null) apply everywhere. When the kind's
 * feature switch is on for a school, that school may also add its own
 * entries and hide portal-wide ones (stored in schools.settings.hidden_terms).
 * The fallback entry ("Inne") can never be hidden, archived or deleted, so
 * every picker always has at least one option.
 */
class TermCatalog
{
    public function __construct(private Features $features) {}

    public function customAllowed(TermKind $kind, ?School $school): bool
    {
        return $school !== null && $this->features->enabled($kind->feature(), $school);
    }

    /**
     * @return list<int>
     */
    public function hiddenIds(TermKind $kind, School $school): array
    {
        return array_values(array_map('intval', (array) (($school->settings ?? [])['hidden_terms'][$kind->value] ?? [])));
    }

    public function isProtected(Term $term): bool
    {
        return $term->isGlobal() && $term->key === $term->kind->fallbackKey();
    }

    /**
     * Active entries offered in a school's pickers and filters. $keep is the
     * value a record already has, so editing never silently changes it.
     *
     * @return Collection<int, Term>
     */
    public function forSchool(TermKind $kind, ?School $school, ?int $keep = null): Collection
    {
        return Term::query()->ofKind($kind)
            ->where(fn (Builder $q) => $this->scopeAvailable($q, $kind, $school)->when($keep, fn ($q) => $q->orWhere('terms.id', $keep)))
            ->ordered()
            ->get();
    }

    /**
     * @return list<int>
     */
    public function allowedIds(TermKind $kind, ?School $school): array
    {
        return Term::query()->ofKind($kind)->where(fn (Builder $q) => $this->scopeAvailable($q, $kind, $school))->pluck('id')->all();
    }

    public function fallback(TermKind $kind, ?School $school): ?Term
    {
        return Term::query()->ofKind($kind)->whereNull('school_id')->where('key', $kind->fallbackKey())->first()
            ?? $this->forSchool($kind, $school)->first();
    }

    /**
     * Everything a manager sees: portal-wide entries (site screen), or
     * portal-wide + this school's own entries (school screen), archived included.
     *
     * @return Collection<int, Term>
     */
    public function forManagement(TermKind $kind, ?School $school): Collection
    {
        return Term::query()->ofKind($kind)
            ->where(fn (Builder $q) => $q->whereNull('school_id')->when($school, fn ($q) => $q->orWhere('school_id', $school->id)))
            ->when($school, fn ($q) => $q->where(fn ($w) => $w->whereNotNull('school_id')->orWhere('is_active', true)))
            ->ordered()
            ->get();
    }

    /**
     * School entries go to that school's log, portal-wide ones to the site journal.
     *
     * @param  array<string, mixed>  $properties
     */
    public function audit(string $action, Term $term, array $properties, ?School $school = null): void
    {
        $schoolId = $school?->id ?? $term->school_id;

        $schoolId === null
            ? Audit::site($action, $term, $properties)
            : Audit::log($action, $term, $properties, schoolId: $schoolId);
    }

    private function scopeAvailable(Builder $query, TermKind $kind, ?School $school): Builder
    {
        $custom = $this->customAllowed($kind, $school);
        $hidden = $custom ? $this->hiddenIds($kind, $school) : [];

        return $query->where(fn (Builder $q) => $q
            ->where(fn ($g) => $g->whereNull('terms.school_id')->where('terms.is_active', true)
                ->when($hidden, fn ($h) => $h->where(fn ($x) => $x->whereNotIn('terms.id', $hidden)->orWhere('terms.key', $kind->fallbackKey()))))
            ->when($custom, fn ($q) => $q->orWhere(fn ($s) => $s->where('terms.school_id', $school->id)->where('terms.is_active', true))));
    }
}
