<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\CurrentSchool;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) || ($actor->can(Permission::UsersManage->value) && $this->inActiveSchool($actor, $user));
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function update(User $actor, User $user): bool
    {
        if (! $actor->can(Permission::UsersManage->value) || ! $this->inActiveSchool($actor, $user)) {
            return false;
        }

        if ($user->is_site_admin && ! $actor->is_site_admin) {
            return false;
        }

        // Only admins may edit other admins (of this school).
        return ! $user->hasRole(Role::Admin->value) || $actor->isAdmin() || $actor->is_site_admin;
    }

    /**
     * Changing roles or direct permissions (incl. alerts.send).
     */
    public function manageAccess(User $actor, User $user): bool
    {
        return $this->update($actor, $user)
            && $actor->can(Permission::RolesManage->value)
            && ! $actor->is($user); // nobody edits their own access
    }

    public function suspend(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $actor->is($user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $this->suspend($actor, $user);
    }

    public function deleteAccount(User $actor, User $user): bool
    {
        return $actor->is_site_admin && ! $actor->is($user);
    }

    public function import(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    /**
     * School admins act only on people of the active school.
     */
    private function inActiveSchool(User $actor, User $user): bool
    {
        $schoolId = app(CurrentSchool::class)->id();

        return $schoolId !== null && $user->schools()->whereKey($schoolId)->exists();
    }
}
