<?php

namespace App\Domain\Groups\Actions;

use App\Enums\GroupRole;
use App\Models\User;
use App\Support\Audit;

/**
 * Replace a user's group memberships with the given set (admin edit form,
 * CSV import). Logs every added / removed / changed membership.
 */
class SyncUserGroups
{
    /**
     * @param  array<int, GroupRole|string>  $memberships  group_id => role
     */
    public function handle(User $user, array $memberships, string $joinedVia = 'admin'): void
    {
        $current = $user->groups()->get()->mapWithKeys(fn ($g) => [$g->id => $g->pivot->role->value])->all();

        $desired = collect($memberships)
            ->mapWithKeys(fn ($role, $groupId) => [(int) $groupId => $role instanceof GroupRole ? $role->value : GroupRole::from($role)->value])
            ->all();

        $sync = [];
        foreach ($desired as $groupId => $role) {
            $sync[$groupId] = isset($current[$groupId])
                ? ['role' => $role]
                : ['role' => $role, 'joined_via' => $joinedVia];
        }

        $changes = $user->groups()->sync($sync);
        $user->flushLeadershipCache();

        $changed = array_values(array_filter(
            array_keys($desired),
            fn ($id) => isset($current[$id]) && $current[$id] !== $desired[$id],
        ));

        if ($changes['attached'] || $changes['detached'] || $changed) {
            Audit::log('user.groups_synced', $user, [
                'attached' => array_values($changes['attached']),
                'detached' => array_values($changes['detached']),
                'role_changed' => $changed,
            ]);
        }
    }
}
