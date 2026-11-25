<?php

namespace App\Policies;

use App\Enums\MembershipMode;
use App\Enums\Permission;
use App\Models\Group;
use App\Models\User;

/**
 * Global: groups.manage may do everything.
 * Scoped: a group leader manages the members (not leaders) of their group.
 */
class GroupPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Group $group): bool
    {
        return $actor->can(Permission::GroupsManage->value)
            || $group->membership_mode === MembershipMode::Open
            || $group->members()->whereKey($actor->id)->exists();
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::GroupsManage->value);
    }

    public function update(User $actor, Group $group): bool
    {
        return $actor->can(Permission::GroupsManage->value) || $actor->leadsGroup($group);
    }

    public function delete(User $actor, Group $group): bool
    {
        return ! $group->is_system && $actor->can(Permission::GroupsManage->value);
    }

    public function manageMembers(User $actor, Group $group): bool
    {
        return $actor->can(Permission::GroupsManage->value) || $actor->leadsGroup($group);
    }

    /**
     * Promoting / demoting leaders, or removing a leader.
     */
    public function manageLeaders(User $actor, Group $group): bool
    {
        return $actor->can(Permission::GroupsManage->value);
    }

    public function join(User $actor, Group $group): bool
    {
        return $group->membership_mode === MembershipMode::Open
            && ! $group->members()->whereKey($actor->id)->exists();
    }

    public function leave(User $actor, Group $group): bool
    {
        return ! $group->is_system
            && $group->membership_mode === MembershipMode::Open
            && $group->members()->whereKey($actor->id)->exists();
    }
}
