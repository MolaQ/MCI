<?php

namespace App\Domain\Groups;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Checks a planned change of one person's group memberships against
 * GroupPolicy, wherever it comes from (user form, CSV import):
 *  - any change to a group (add, remove, role) needs manageMembers;
 *  - anything that makes, unmakes or removes a leader needs manageLeaders.
 */
class MembershipGuard
{
    /**
     * @param  array<int|string, string>  $current  group_id => role
     * @param  array<int|string, string>  $desired  group_id => role
     * @return list<string> names of the groups the actor may not change this way
     */
    public function denied(User $actor, array $current, array $desired): array
    {
        $changed = [];

        foreach (array_unique([...array_keys($current), ...array_keys($desired)]) as $id) {
            $old = $current[$id] ?? null;
            $new = $desired[$id] ?? null;

            if ($old !== $new) {
                $changed[(int) $id] = in_array(GroupRole::Leader->value, [$old, $new], true);
            }
        }

        if (! $changed) {
            return [];
        }

        $gate = Gate::forUser($actor);

        return Group::query()->whereKey(array_keys($changed))->orderBy('name')->get()
            ->reject(fn (Group $group) => $gate->allows('manageMembers', $group)
                && (! $changed[$group->id] || $gate->allows('manageLeaders', $group)))
            ->pluck('name')->values()->all();
    }

    /**
     * @return array<int, string> group_id => role
     */
    public static function currentOf(?User $user): array
    {
        return $user
            ? $user->groups()->get()->mapWithKeys(fn ($g) => [$g->id => $g->pivot->role->value])->all()
            : [];
    }
}
