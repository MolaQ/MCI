<?php

namespace App\Domain\Groups\Actions;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use App\Support\Audit;

class AddGroupMember
{
    public function handle(Group $group, User $user, GroupRole $role = GroupRole::Member, string $joinedVia = 'admin'): void
    {
        if ($group->members()->whereKey($user->id)->exists()) {
            $this->changeRole($group, $user, $role);

            return;
        }

        $group->members()->attach($user->id, ['role' => $role->value, 'joined_via' => $joinedVia]);
        $user->flushLeadershipCache();

        Audit::log('group.member_added', $group, ['user_id' => $user->id, 'role' => $role->value]);
    }

    public function changeRole(Group $group, User $user, GroupRole $role): void
    {
        $updated = $group->members()->updateExistingPivot($user->id, ['role' => $role->value]);
        $user->flushLeadershipCache();

        if ($updated) {
            Audit::log('group.member_role_changed', $group, ['user_id' => $user->id, 'role' => $role->value]);
        }
    }

    public function remove(Group $group, User $user): void
    {
        if ($group->members()->detach($user->id)) {
            $user->flushLeadershipCache();
            Audit::log('group.member_removed', $group, ['user_id' => $user->id]);
        }
    }
}
