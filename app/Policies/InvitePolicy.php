<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invite;
use App\Models\User;
use App\Support\CurrentSchool;

class InvitePolicy
{
    public function viewAny(User $actor): bool
    {
        return app(CurrentSchool::class)->id() !== null
            && ($actor->can(Permission::InvitesCreate->value) || $actor->leadsAnyGroup());
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, Invite $invite): bool
    {
        return $invite->school_id === app(CurrentSchool::class)->id()
            && ($actor->can(Permission::InvitesCreate->value) || $invite->created_by === $actor->id);
    }

    public function revoke(User $actor, Invite $invite): bool
    {
        return $this->view($actor, $invite) && $invite->revoked_at === null;
    }
}
