<?php

namespace App\Domain\Catalog\Actions;

use App\Enums\Permission;
use App\Enums\TaxonomyKind;
use App\Models\School;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Support\Audit;
use App\Support\Taxonomy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Create, edit, reorder, archive, delete and hide event categories and group
 * types. $school = null works on the site-wide list (site admin only); a
 * school works on that school's own list (categories.manage in the school,
 * and only while the site lets schools keep their own list). Every change
 * is audited: site list → Dziennik witryny, school list → Logi szkoły.
 */
class ManageTerms
{
    /** Monochrome icons offered in the picker (Heroicons, drawn in currentColor). */
    public const ICONS = [
        'calendar', 'calendar-days', 'building-library', 'trophy', 'academic-cap', 'sparkles', 'flag', 'heart',
        'users', 'user-group', 'briefcase', 'book-open', 'wrench-screwdriver', 'light-bulb', 'building-office', 'tag',
        'megaphone', 'musical-note', 'beaker', 'globe-europe-africa', 'computer-desktop', 'paint-brush', 'camera', 'film',
        'map-pin', 'puzzle-piece', 'rocket-launch', 'shield-check', 'star', 'sun', 'gift', 'chat-bubble-left-right',
        'presentation-chart-bar', 'clipboard-document-list', 'cake', 'bolt', 'scale', 'language', 'cpu-chip', 'hand-raised',
        'home-modern', 'truck', 'ticket', 'cog-6-tooth',
    ];

    public function __construct(private Taxonomy $taxonomy) {}

    public function authorize(User $user, TaxonomyKind $kind, ?School $school): void
    {
        $allowed = $school === null
            ? $user->can('site-admin')
            : $user->can(Permission::CategoriesManage->value) && $this->taxonomy->schoolMayCustomise($kind, $school);

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    /** @param array{name?: mixed, icon?: mixed, color?: mixed} $data */
    public function create(User $user, TaxonomyKind $kind, ?School $school, array $data): TaxonomyTerm
    {
        $this->authorize($user, $kind, $school);
        $data = $this->validate($kind, $school, $data);

        $term = TaxonomyTerm::query()->create($data + [
            'kind' => $kind,
            'school_id' => $school?->id,
            'key' => $this->uniqueKey($kind, $school, $data['name']),
            'position' => (int) TaxonomyTerm::query()->ofKind($kind)->where('school_id', $school?->id)->max('position') + 1,
        ]);

        $this->audit($school, 'catalog.created', $term, ['kind' => $kind->value, 'name' => $term->name]);

        return $term;
    }

    /** Rename / change the icon or colour. The key never changes, so records keep pointing at it. */
    public function update(User $user, TaxonomyTerm $term, ?School $school, array $data): TaxonomyTerm
    {
        $this->guardScope($user, $term, $school);
        $data = $this->validate($term->kind, $school, $data, $term);

        $before = $term->only(['name', 'icon', 'color']);
        $term->update($data);
        $changes = array_diff_assoc(array_map('strval', $term->only(['name', 'icon', 'color'])), array_map('strval', $before));

        if ($changes !== []) {
            $this->audit($school, 'catalog.updated', $term, ['kind' => $term->kind->value, 'from' => array_intersect_key($before, $changes), 'to' => $changes]);
        }

        return $term;
    }

    /** Move one place up (-1) or down (+1) within its own list. */
    public function move(User $user, TaxonomyTerm $term, ?School $school, int $direction): void
    {
        $this->guardScope($user, $term, $school);

        DB::transaction(function () use ($term, $direction, $school) {
            $list = TaxonomyTerm::query()->ofKind($term->kind)->where('school_id', $term->school_id)
                ->orderBy('position')->orderBy('id')->lockForUpdate()->get()->values();
            $from = $list->search(fn ($t) => $t->id === $term->id);
            $to = $from + ($direction < 0 ? -1 : 1);
            if ($from === false || $to < 0 || $to >= $list->count()) {
                return;
            }

            $ids = $list->pluck('id')->all();
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            foreach ($ids as $position => $id) {
                TaxonomyTerm::query()->whereKey($id)->update(['position' => $position]);
            }

            $this->audit($school, 'catalog.reordered', $term, ['kind' => $term->kind->value, 'name' => $term->name, 'position' => $to + 1]);
        });

        $this->taxonomy->forget();
    }

    /** Archived terms are not offered for new records; existing records keep showing them. */
    public function setActive(User $user, TaxonomyTerm $term, ?School $school, bool $active): void
    {
        $this->guardScope($user, $term, $school);
        $term->update(['is_active' => $active]);
        $this->audit($school, $active ? 'catalog.restored' : 'catalog.archived', $term, ['kind' => $term->kind->value, 'name' => $term->name]);
    }

    /** Only unused terms can be deleted; used ones can be archived. */
    public function delete(User $user, TaxonomyTerm $term, ?School $school): void
    {
        $this->guardScope($user, $term, $school);

        if ($this->usage($term) > 0) {
            throw ValidationException::withMessages(['term' => __('Tej pozycji używają istniejące wpisy. Zamiast usuwać, zarchiwizuj ją.')]);
        }

        $this->audit($school, 'catalog.deleted', $term, ['kind' => $term->kind->value, 'name' => $term->name, 'key' => $term->key]);
        $term->delete();
        $this->taxonomy->forget();
    }

    /** A school hides (or shows again) a site-wide term in its own lists. */
    public function setHidden(User $user, TaxonomyTerm $term, School $school, bool $hidden): void
    {
        $this->authorize($user, $term->kind, $school);
        abort_unless($term->isGlobal(), 404);

        $this->taxonomy->setHidden($term->kind, $school, $term->key, $hidden);
        $this->audit($school, $hidden ? 'catalog.hidden' : 'catalog.shown', $term, ['kind' => $term->kind->value, 'name' => $term->name]);
    }

    /** How many records use the term: in its school, or everywhere for a site-wide term. */
    public function usage(TaxonomyTerm $term, ?int $inSchool = null): int
    {
        $schoolId = $term->school_id ?? $inSchool;

        return $term->kind->usage()
            ->where($term->kind->column(), $term->key)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            // A site-wide term is shadowed in schools that have their own term with the same key.
            ->when($term->isGlobal() && ! $inSchool, fn ($q) => $q->whereNotIn('school_id', TaxonomyTerm::query()
                ->ofKind($term->kind)->where('key', $term->key)->whereNotNull('school_id')->select('school_id')))
            ->count();
    }

    private function guardScope(User $user, TaxonomyTerm $term, ?School $school): void
    {
        $this->authorize($user, $term->kind, $school);
        // A school edits only its own terms; the site list only site-wide ones.
        abort_unless($term->school_id === $school?->id, 404);
    }

    private function validate(TaxonomyKind $kind, ?School $school, array $data, ?TaxonomyTerm $term = null): array
    {
        $data = [
            'name' => trim((string) ($data['name'] ?? '')),
            'icon' => (string) ($data['icon'] ?? ''),
            'color' => $kind->hasColor() ? (($data['color'] ?? '') ?: null) : null,
        ];

        // Names must differ within what people see together: the site list, or the site list plus the school's own.
        $names = TaxonomyTerm::query()->ofKind($kind)
            ->where(fn ($q) => $q->whereNull('school_id')->when($school, fn ($w) => $w->orWhere('school_id', $school->id)))
            ->when($term, fn ($q) => $q->whereKeyNot($term->id))
            ->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();

        Validator::make($data, [
            'name' => ['required', 'string', 'min:2', 'max:60', function ($attr, $value, $fail) use ($names) {
                if (in_array(mb_strtolower($value), $names, true)) {
                    $fail(__('Pozycja o tej nazwie już istnieje.'));
                }
            }],
            'icon' => ['required', Rule::in(self::ICONS)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ['name' => __('nazwa'), 'icon' => __('ikona'), 'color' => __('kolor')])->validate();

        $data['color'] = $data['color'] ? strtolower($data['color']) : null;

        return $data;
    }

    /** Key = slug of the first name, unique in the list it can be seen with. */
    private function uniqueKey(TaxonomyKind $kind, ?School $school, string $name): string
    {
        $base = Str::limit(Str::slug(Str::ascii($name), '_'), 34, '') ?: 'pozycja';
        $taken = TaxonomyTerm::query()->ofKind($kind)
            ->when($school, fn ($q) => $q->where(fn ($w) => $w->whereNull('school_id')->orWhere('school_id', $school->id)))
            ->pluck('key')->all();

        $key = $base;
        for ($i = 2; in_array($key, $taken, true); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }

    private function audit(?School $school, string $action, TaxonomyTerm $term, array $properties): void
    {
        $this->taxonomy->forget();
        $school
            ? Audit::log($action, $term, $properties, schoolId: $school->id)
            : Audit::site($action, $term, $properties);
    }
}
