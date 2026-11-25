<?php

namespace App\Domain\Tasks\Actions;

use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Ticks a checklist item (author, assignees and managers). */
class ToggleChecklistItem
{
    public function handle(User $actor, TaskChecklistItem $item): TaskChecklistItem
    {
        if (Gate::forUser($actor)->denies('work', $item->task)) {
            throw new AuthorizationException(__('Nie możesz zmieniać tego zadania.'));
        }

        $done = ! $item->is_done;
        $item->forceFill(['is_done' => $done, 'done_by' => $done ? $actor->id : null, 'done_at' => $done ? now() : null])->save();

        return $item;
    }
}
