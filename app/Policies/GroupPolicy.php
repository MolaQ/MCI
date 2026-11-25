<?php

namespace App\Policies;

use App\Enums\Feature;
use App\Enums\MembershipMode;
use App\Enums\Permission;
use App\Models\Group;
use App\Models\User;
use App\Support\CurrentSchool;
use App\Support\Features;

/**
 * Groups always belong to one school and are only reachable while that
 * school is active. Global: groups.manage may do everything in the school.
 * Scoped: a group leader manages the members (not leaders) of their group.
 */
class GroupPolicy
{
    public function viewAny(User $actor): bool
    {
        return app(CurrentSchool::class)->id() !== null;
    }

    public function view(User $actor, Group $group): bool
    {
        return $this->inActiveSchool($group) && (
            $actor->can(Permission::GroupsManage->value)
            || $group->membership_mode === MembershipMode::Open
            || $group->members()->whereKey($actor->id)->exists()
        );
    }

    public function create(User $actor): bool
    {
        return app(CurrentSchool::class)->id() !== null && $actor->can(Permission::GroupsManage->value);
    }

    public function update(User $actor, Group $group): bool
    {
        return $this->inActiveSchool($group)
            && ($actor->can(Permission::GroupsManage->value) || $actor->leadsGroup($group));
    }

    public function delete(User $actor, Group $group): bool
    {
        return $this->inActiveSchool($group) && ! $group->is_system && $actor->can(Permission::GroupsManage->value);
    }

    public function manageMembers(User $actor, Group $group): bool
    {
        return $this->update($actor, $group);
    }

    /**
     * Promoting / demoting leaders, or removing a leader.
     */
    public function manageLeaders(User $actor, Group $group): bool
    {
        return $this->inActiveSchool($group) && $actor->can(Permission::GroupsManage->value);
    }

    /**
     * Creating an invite / QR code for this group.
     */
    public function invite(User $actor, Group $group): bool
    {
        return $this->inActiveSchool($group)
            && ($actor->can(Permission::InvitesCreate->value) || $actor->leadsGroup($group));
    }

    public function join(User $actor, Group $group): bool
    {
        return $this->inActiveSchool($group)
            && $group->membership_mode === MembershipMode::Open
            && app(Features::class)->enabled(Feature::GroupSelfJoin, $group->school_id)
            && ! $group->members()->whereKey($actor->id)->exists();
    }

    public function leave(User $actor, Group $group): bool
    {
        return $this->inActiveSchool($group)
            && ! $group->is_system
            && $group->membership_mode === MembershipMode::Open
            && app(Features::class)->enabled(Feature::GroupSelfJoin, $group->school_id)
            && $group->members()->whereKey($actor->id)->exists();
    }

    private function inActiveSchool(Group $group): bool
    {
        return $group->school_id === app(CurrentSchool::class)->id();
    }
}
