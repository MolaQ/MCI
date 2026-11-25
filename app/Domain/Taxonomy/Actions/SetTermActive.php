<?php

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Taxonomy\TermCatalog;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Archive (or restore) an entry: it disappears from pickers and filters,
 * while events and groups that already use it keep showing it.
 */
class SetTermActive
{
    public function __construct(private TermCatalog $catalog) {}

    public function handle(User $actor, Term $term, bool $active): void
    {
        Gate::forUser($actor)->authorize('update', $term);

        if (! $active && $this->catalog->isProtected($term)) {
            throw ValidationException::withMessages(['term' => __('Tej pozycji nie można zarchiwizować. Zawsze musi być dostępna.')]);
        }
        if ($term->is_active === $active) {
            return;
        }

        $term->update(['is_active' => $active]);
        $this->catalog->audit($active ? 'term.restored' : 'term.archived', $term, ['kind' => $term->kind->value, 'name' => $term->name]);
    }
}
