<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\ProjectGuard;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Milestones (stages) of a project: add / edit / complete / reopen /
 * move / delete. Editing is for people who manage the project; ticking a
 * milestone off is open to the whole team.
 */
class ManageMilestones
{
    public const MAX = 40;

    /**
     * @param  array{title?: string, description?: ?string, due_date?: ?string, due_time?: ?string}  $data
     */
    public function save(User $actor, Project $project, array $data, ?ProjectMilestone $milestone = null): ProjectMilestone
    {
        Gate::forUser($actor)->authorize('update', $project);
        abort_if($milestone && $milestone->project_id !== $project->id, 404);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) {
            throw ValidationException::withMessages(['milestoneTitle' => __('Podaj nazwę kamienia milowego (do 200 znaków).')]);
        }
        if (! $milestone && $project->milestones()->count() >= self::MAX) {
            throw ValidationException::withMessages(['milestoneTitle' => __('Projekt może mieć najwyżej :count kamieni milowych.', ['count' => self::MAX])]);
        }

        $dueAt = null;
        if (filled($data['due_date'] ?? null)) {
            try {
                $time = filled($data['due_time'] ?? null) ? $data['due_time'] : '23:59';
                $dueAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['due_date'].' '.$time, config('app.timezone'));
            } catch (\Throwable) {
                throw ValidationException::withMessages(['milestoneDate' => __('Nieprawidłowy termin.')]);
            }
        }

        $milestone?->refresh();
        $milestone ??= new ProjectMilestone(['project_id' => $project->id, 'position' => (int) $project->milestones()->max('position') + 1]);
        $dueChanged = $milestone->due_at?->toIso8601String() !== $dueAt?->toIso8601String();
        $milestone->fill([
            'title' => $title,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'due_at' => $dueAt,
        ]);
        if ($dueChanged) {
            // A new deadline earns new reminders.
            $milestone->reminded_at = null;
            $milestone->overdue_notified_at = null;
        }
        $milestone->save();

        ProjectGuard::audit($project, 'project.milestone_saved', ['milestone' => $milestone->title, 'due_at' => $milestone->due_at?->toIso8601String()], $actor);

        return $milestone;
    }

    public function toggle(User $actor, ProjectMilestone $milestone): ProjectMilestone
    {
        $project = $milestone->project;
        Gate::forUser($actor)->authorize('work', $project);

        $done = $milestone->completed_at === null;
        $milestone->update(['completed_at' => $done ? now() : null, 'completed_by' => $done ? $actor->id : null]);
        ProjectGuard::audit($project, $done ? 'project.milestone_completed' : 'project.milestone_reopened', ['milestone' => $milestone->title], $actor);

        return $milestone;
    }

    public function move(User $actor, ProjectMilestone $milestone, int $direction): void
    {
        $project = $milestone->project;
        Gate::forUser($actor)->authorize('update', $project);

        $list = $project->milestones()->get()->values();
        $index = $list->search(fn ($m) => $m->id === $milestone->id);
        $target = $index + ($direction < 0 ? -1 : 1);
        if ($index === false || ! isset($list[$target])) {
            return;
        }
        [$list[$index], $list[$target]] = [$list[$target], $list[$index]];
        foreach ($list as $position => $m) {
            $m->update(['position' => $position + 1]);
        }
    }

    public function delete(User $actor, ProjectMilestone $milestone): void
    {
        $project = $milestone->project;
        Gate::forUser($actor)->authorize('update', $project);

        $milestone->delete();
        ProjectGuard::audit($project, 'project.milestone_deleted', ['milestone' => $milestone->title], $actor);
    }
}
