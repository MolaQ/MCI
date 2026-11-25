<?php

namespace App\Policies;

use App\Enums\ContentVisibility;
use App\Enums\Permission;
use App\Models\Event;
use App\Models\User;
use App\Support\CurrentSchool;

class EventPolicy
{
    public function view(?User $actor, Event $event): bool
    {
        return match ($event->visibility) {
            ContentVisibility::Public => true,
            ContentVisibility::InterSchool => $actor !== null && $actor->schools()->wherePivot('status', 'active')->exists(),
            ContentVisibility::SchoolOnly => $actor?->belongsToSchool($event->school_id) ?? false,
            ContentVisibility::PrivateGroup => $actor !== null && ($event->owner_id === $actor->id || $this->manages($actor, $event)),
        };
    }

    public function create(User $actor): bool
    {
        return app(CurrentSchool::class)->id() !== null && $actor->can(Permission::EventsCreate->value);
    }

    /** The author, or someone who may publish public content in that school. */
    public function update(User $actor, Event $event): bool
    {
        return app(CurrentSchool::class)->id() === $event->school_id
            && ($event->owner_id === $actor->id || $this->manages($actor, $event));
    }

    public function delete(User $actor, Event $event): bool
    {
        return $this->update($actor, $event);
    }

    public function publishPublic(User $actor): bool
    {
        return $actor->can(Permission::EventsPublishPublic->value);
    }

    private function manages(User $actor, Event $event): bool
    {
        return app(CurrentSchool::class)->id() === $event->school_id
            && $actor->can(Permission::EventsPublishPublic->value);
    }
}
