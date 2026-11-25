<?php

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Taxonomy\TermCatalog;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * A school hides (or shows again) a portal-wide entry it does not use.
 * Other schools are not affected.
 */
class HideTermInSchool
{
    public function __construct(private TermCatalog $catalog) {}

    public function handle(User $actor, Term $term, School $school, bool $hidden): void
    {
        Gate::forUser($actor)->authorize('hide', [$term, $school]);

        $ids = $this->catalog->hiddenIds($term->kind, $school);
        $next = $hidden ? array_values(array_unique([...$ids, $term->id])) : array_values(array_diff($ids, [$term->id]));
        if ($next === $ids) {
            return;
        }

        $settings = $school->settings ?? [];
        $settings['hidden_terms'][$term->kind->value] = $next;
        $school->forceFill(['settings' => $settings])->save();

        $this->catalog->audit($hidden ? 'term.hidden' : 'term.shown', $term, ['kind' => $term->kind->value, 'name' => $term->name], $school);
    }
}
