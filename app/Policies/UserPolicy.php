<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) || $actor->can(Permission::UsersManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function update(User $actor, User $user): bool
    {
        if (! $actor->can(Permission::UsersManage->value)) {
            return false;
        }

        // Only admins may edit other admins.
        return ! $user->hasRole(Role::Admin->value) || $actor->isAdmin();
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

    public function import(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }
}
