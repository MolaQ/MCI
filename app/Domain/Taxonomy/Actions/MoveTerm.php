<?php

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Taxonomy\TermCatalog;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Move an entry one place up or down within its own list (portal-wide
 * entries and a school's own entries are ordered separately).
 */
class MoveTerm
{
    public function __construct(private TermCatalog $catalog) {}

    public function handle(User $actor, Term $term, string $direction): void
    {
        Gate::forUser($actor)->authorize('update', $term);

        DB::transaction(function () use ($term, $direction) {
            $siblings = Term::query()->ofKind($term->kind)->where('school_id', $term->school_id)
                ->orderBy('position')->orderBy('id')->lockForUpdate()->get(['id', 'position'])->values();

            $index = $siblings->search(fn (Term $t) => $t->id === $term->id);
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || $target < 0 || $target >= $siblings->count()) {
                return;
            }

            $ids = $siblings->pluck('id')->all();
            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
            foreach ($ids as $position => $id) {
                Term::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        $this->catalog->audit('term.reordered', $term, ['kind' => $term->kind->value, 'name' => $term->name, 'direction' => $direction]);
    }
}
