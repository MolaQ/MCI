<?php

namespace App\Policies;

use App\Domain\Communication\AudienceGuard;
use App\Enums\Permission;
use App\Models\Announcement;
use App\Models\User;
use App\Support\CurrentSchool;

class AnnouncementPolicy
{
    public function create(User $actor): bool
    {
        return app(CurrentSchool::class)->id() !== null && app(AudienceGuard::class)->canPublish($actor);
    }

    /**
     * Recipients and the author always; managers of that school (publish.any)
     * for oversight. Scheduled posts stay hidden from recipients until live.
     */
    public function view(User $actor, Announcement $announcement): bool
    {
        if ($announcement->author_id === $actor->id) {
            return true;
        }

        if ($this->manages($actor, $announcement)) {
            return true;
        }

        return $announcement->isLive()
            && $announcement->recipients()->whereKey($actor->id)->exists();
    }

    /** Read statistics (who read / confirmed). */
    public function viewStats(User $actor, Announcement $announcement): bool
    {
        return $announcement->author_id === $actor->id || $this->manages($actor, $announcement);
    }

    public function delete(User $actor, Announcement $announcement): bool
    {
        return $announcement->author_id === $actor->id || $this->manages($actor, $announcement);
    }

    private function manages(User $actor, Announcement $announcement): bool
    {
        return app(CurrentSchool::class)->id() === $announcement->school_id
            && $actor->can(Permission::AnnouncementsPublishAny->value);
    }
}
