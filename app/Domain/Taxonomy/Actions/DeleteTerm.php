<?php

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Taxonomy\TermCatalog;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Delete an entry nobody uses. Entries in use can only be archived, so
 * history (old events, groups) never loses its category.
 */
class DeleteTerm
{
    public function __construct(private TermCatalog $catalog) {}

    public function handle(User $actor, Term $term): void
    {
        Gate::forUser($actor)->authorize('delete', $term);

        $used = $term->usageCount();
        if ($used > 0) {
            throw ValidationException::withMessages(['term' => trans_choice(
                'Ta pozycja jest używana (:count raz). Możesz ją zarchiwizować.|Ta pozycja jest używana (:count razy). Możesz ją zarchiwizować.|Ta pozycja jest używana (:count razy). Możesz ją zarchiwizować.',
                $used,
            )]);
        }

        DB::transaction(function () use ($term) {
            $this->catalog->audit('term.deleted', $term, ['kind' => $term->kind->value, 'name' => $term->name]);

            // Forget it in the schools that had hidden it.
            if ($term->isGlobal()) {
                School::query()->whereNotNull('settings')->each(function (School $school) use ($term) {
                    $hidden = $this->catalog->hiddenIds($term->kind, $school);
                    if (in_array($term->id, $hidden, true)) {
                        $settings = $school->settings;
                        $settings['hidden_terms'][$term->kind->value] = array_values(array_diff($hidden, [$term->id]));
                        $school->forceFill(['settings' => $settings])->save();
                    }
                });
            }

            $term->delete();
        });
    }
}
