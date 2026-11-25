<?php

namespace App\Domain\Groups\Actions;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use App\Support\Audit;
use App\Support\CurrentSchool;

/**
 * Replace a user's memberships *within one school* with the given set
 * (admin edit form, CSV import). Groups of other schools are never touched.
 */
class SyncUserGroups
{
    public function __construct(private CurrentSchool $current) {}

    /**
     * @param  array<int|string, GroupRole|string>  $memberships  group_id => role
     */
    public function handle(User $user, array $memberships, string $joinedVia = 'admin', ?int $schoolId = null): void
    {
        $schoolId ??= $this->current->id();

        $current = $user->groups()->where('groups.school_id', $schoolId)->get()
            ->mapWithKeys(fn ($g) => [$g->id => $g->pivot->role->value])->all();

        $desired = collect($memberships)
            ->mapWithKeys(fn ($role, $groupId) => [(int) $groupId => $role instanceof GroupRole ? $role->value : GroupRole::from($role)->value])
            ->all();

        // Only groups of this school may be attached.
        $allowed = Group::query()->where('school_id', $schoolId)->whereKey(array_keys($desired))->pluck('id')->all();
        $desired = array_intersect_key($desired, array_flip($allowed));

        $attached = array_diff_key($desired, $current);
        $detached = array_keys(array_diff_key($current, $desired));
        $changed = array_keys(array_filter($desired, fn ($role, $id) => isset($current[$id]) && $current[$id] !== $role, ARRAY_FILTER_USE_BOTH));

        foreach ($attached as $groupId => $role) {
            $user->groups()->attach($groupId, ['role' => $role, 'joined_via' => $joinedVia]);
        }
        if ($detached) {
            $user->groups()->detach($detached);
        }
        foreach ($changed as $groupId) {
            $user->groups()->updateExistingPivot($groupId, ['role' => $desired[$groupId]]);
        }

        $user->flushLeadershipCache();

        if ($attached || $detached || $changed) {
            Audit::log('user.groups_synced', $user, [
                'attached' => array_keys($attached),
                'detached' => $detached,
                'role_changed' => $changed,
            ], schoolId: $schoolId);
        }
    }
}
