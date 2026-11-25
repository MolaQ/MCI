<?php

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Taxonomy\TermCatalog;
use App\Enums\TermKind;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Create or edit a dictionary entry. School entries go to the school log,
 * portal-wide entries to the site journal.
 */
class SaveTerm
{
    public function __construct(private TermCatalog $catalog) {}

    /**
     * @param  array{name?: string, icon?: string, color?: ?string, is_featured?: bool}  $input
     */
    public function handle(User $actor, TermKind $kind, ?School $school, array $input, ?Term $term = null): Term
    {
        if ($term) {
            Gate::forUser($actor)->authorize('update', $term);
            $kind = $term->kind;
            $school = $term->isGlobal() ? null : School::query()->findOrFail($term->school_id);
        } else {
            Gate::forUser($actor)->authorize('create', [Term::class, $kind, $school]);
        }

        $input['name'] = Str::squish((string) ($input['name'] ?? ''));
        $input['color'] = ($input['color'] ?? null) ?: null;

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:60', function ($attribute, $value, $fail) use ($kind, $school, $term) {
                if ($this->nameTaken($kind, $school, $value, $term)) {
                    $fail(__('Taka nazwa już istnieje na tej liście.'));
                }
            }],
            'icon' => ['required', Rule::in(TermKind::ICONS)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_featured' => ['boolean'],
        ], [], ['name' => __('nazwa'), 'icon' => __('ikona'), 'color' => __('kolor')])->validate();

        $attributes = [
            'name' => $data['name'],
            'icon' => $data['icon'],
            'color' => $kind->usesColor() ? ($data['color'] ?? null) : null,
            'is_featured' => $kind->usesFeatured() && ($data['is_featured'] ?? false),
        ];

        if ($term) {
            $term->update($attributes);
            $changed = array_keys($term->getChanges());
            if ($changed !== [] && $changed !== ['updated_at']) {
                $this->catalog->audit('term.updated', $term, ['kind' => $kind->value, 'name' => $term->name, 'changed' => array_values(array_diff($changed, ['updated_at']))]);
            }

            return $term;
        }

        $term = Term::query()->create($attributes + [
            'kind' => $kind,
            'school_id' => $school?->id,
            'key' => $this->uniqueKey($kind, $school, $data['name']),
            'position' => (int) Term::query()->ofKind($kind)->where('school_id', $school?->id)->max('position') + 1,
            'created_by' => $actor->id,
        ]);
        $this->catalog->audit('term.created', $term, ['kind' => $kind->value, 'name' => $term->name]);

        return $term;
    }

    private function nameTaken(TermKind $kind, ?School $school, string $name, ?Term $except): bool
    {
        return Term::query()->ofKind($kind)
            ->where(fn ($q) => $q->whereNull('school_id')->when($school, fn ($q) => $q->orWhere('school_id', $school->id)))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get(['name'])
            ->contains(fn (Term $t) => mb_strtolower($t->name) === mb_strtolower($name));
    }

    private function uniqueKey(TermKind $kind, ?School $school, string $name): string
    {
        $base = Str::limit(Str::slug($name, '_'), 50, '') ?: 'pozycja';
        $key = $base;
        for ($i = 2; Term::query()->ofKind($kind)->where('school_id', $school?->id)->where('key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }
}
