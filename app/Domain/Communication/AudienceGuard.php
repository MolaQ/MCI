<?php

namespace App\Domain\Communication;

use App\Enums\Permission;
use App\Models\Group;
use App\Models\User;

/**
 * Who may address whom (docs/ARCHITECTURE.md §4.3). Used by the composer,
 * by PublishAnnouncement (server-side, never trusting the form) and by
 * group mentions: a mention notifies a group only if the author could have
 * targeted it.
 *
 *  - announcements.publish.any        → whole school, roles, any group (+ subgroups)
 *  - announcements.publish.own_groups → groups the author belongs to or leads
 *                                       (subgroups only where they lead)
 *  - both                             → individual people of the school
 */
class AudienceGuard
{
    public function canPublish(User $author): bool
    {
        return $author->can(Permission::AnnouncementsPublishAny->value)
            || $author->can(Permission::AnnouncementsPublishOwnGroups->value);
    }

    public function canTargetWholeSchool(User $author): bool
    {
        return $author->can(Permission::AnnouncementsPublishAny->value);
    }

    public function canTargetGroup(User $author, Group $group): bool
    {
        if ($author->can(Permission::AnnouncementsPublishAny->value)) {
            return true;
        }

        return $author->can(Permission::AnnouncementsPublishOwnGroups->value)
            && $group->members()->whereKey($author->id)->exists();
    }

    public function canIncludeSubgroups(User $author, Group $group): bool
    {
        return $author->can(Permission::AnnouncementsPublishAny->value) || $author->leadsGroup($group);
    }

    /**
     * Groups of the active school this author may address.
     */
    public function targetableGroups(User $author)
    {
        return Group::query()->inCurrentSchool()
            ->unless($author->can(Permission::AnnouncementsPublishAny->value),
                fn ($q) => $author->can(Permission::AnnouncementsPublishOwnGroups->value)
                    ? $q->whereHas('members', fn ($m) => $m->whereKey($author->id))
                    : $q->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();
    }
}
