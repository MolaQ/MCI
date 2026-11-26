<?php

namespace App\Domain\Calendar;

use App\Enums\ContentVisibility;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\School;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "What is happening at school": the items of the agenda, the month
 * calendar and the project timeline, for a signed-in person (internal data
 * they may see) or for the public front and the TV board (public data
 * only). Every module is read only when its feature is on.
 *
 * Calendar modes for signed-in people:
 *  - personal ("Mój kalendarz"): school events + my task deadlines,
 *    milestones of my projects and my pending decisions;
 *  - school ("Kalendarz szkoły"): school events + milestones of every
 *    project of the school I may see, no personal tasks.
 */
class Agenda
{
    public const MODES = ['personal', 'school'];

    public function __construct(private readonly Features $features) {}

    /** The person's choice, else the school's default, else the site default. */
    public static function defaultMode(?User $user, ?School $school): string
    {
        $mode = $user?->notification_prefs['calendar'] ?? null;
        $mode = in_array($mode, self::MODES, true) ? $mode : $school?->setting('calendar_default');

        return in_array($mode, self::MODES, true) ? $mode : (string) config('mci.calendar.default', 'personal');
    }

    /**
     * @return Collection<int, CalendarItem>
     */
    public function forUser(User $user, School $school, string $mode, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $from = $from->toImmutable();
        $to = $to->toImmutable();
        $on = fn (Feature $f) => $this->features->enabled($f, $school);
        $items = collect();

        if ($on(Feature::Events)) {
            $events = Event::query()->visibleTo($user)
                ->where('events.school_id', $school->id)
                ->where('events.status', '!=', 'cancelled');
            $items = $items->merge($this->overlapping($events, $from, $to)->get()->map(fn (Event $e) => $this->event($e, route('events.show', $e))));
        }

        if ($mode === 'personal' && $on(Feature::Tasks)) {
            $items = $items->merge(Task::query()->assignedTo($user)->open()
                ->where('tasks.school_id', $school->id)
                ->whereBetween('tasks.due_at', [$from, $to])
                ->get()->map(fn (Task $t) => new CalendarItem(
                    kind: 'task', title: $t->title, start: $t->due_at->toImmutable(), allDay: (bool) $t->all_day || $t->due_at->format('H:i') === '23:59',
                    url: route('tasks.show', $t), icon: 'check-circle', tone: $t->isOverdue() ? 'red' : 'amber',
                    meta: __('Zadanie'), alert: $t->isOverdue(),
                )));
        }

        if ($on(Feature::Projects)) {
            $projects = Project::query()->inSchools([$school->id])
                ->when($mode === 'personal', fn ($q) => $q->involving($user), fn ($q) => $q->visibleTo($user, $user->can(Permission::ProjectsManage->value) ? $school->id : null));
            $items = $items->merge(ProjectMilestone::query()
                ->whereIn('project_id', $projects->select('projects.id'))
                ->whereBetween('due_at', [$from, $to])
                ->with('project:id,name')
                ->get()->map(fn (ProjectMilestone $m) => $this->milestone($m, route('projects.show', $m->project_id))));
        }

        if ($mode === 'personal' && $on(Feature::Workflows)) {
            $items = $items->merge(WorkflowRequest::query()->awaitingDecisionBy($user)
                ->where('workflow_requests.school_id', $school->id)
                ->whereBetween('workflow_requests.stage_due_at', [$from, $to])
                ->get()->map(fn (WorkflowRequest $r) => new CalendarItem(
                    kind: 'decision', title: $r->title, start: $r->stage_due_at->toImmutable(),
                    url: route('workflows.show', $r), icon: 'clipboard-document-check', tone: 'red',
                    meta: __('Decyzja'), alert: $r->isOverdue(),
                )));
        }

        return $items->values();
    }

    /**
     * Public events (and milestones of public projects) of listed schools.
     *
     * @return Collection<int, CalendarItem>
     */
    public function forPublic(?int $schoolId, CarbonInterface $from, CarbonInterface $to, ?string $category = null): Collection
    {
        $from = $from->toImmutable();
        $to = $to->toImmutable();
        $items = collect();

        $events = Event::query()->publiclyVisible()
            ->with('school:id,short_name')
            ->when($schoolId, fn ($q) => $q->where('events.school_id', $schoolId))
            ->when($category, fn ($q) => $q->where('events.category', $category));
        $items = $items->merge($this->overlapping($events, $from, $to)->get()
            ->map(fn (Event $e) => $this->event($e, route('public.event', $e), $schoolId ? null : $e->school?->short_name)));

        if (! $category) {
            $items = $items->merge(ProjectMilestone::query()
                ->whereIn('project_id', Project::query()->publiclyVisible()->when($schoolId, fn ($q) => $q->where('projects.school_id', $schoolId))->reorder()->select('projects.id'))
                ->whereBetween('due_at', [$from, $to])
                ->with('project:id,name')
                ->get(['id', 'project_id', 'title', 'due_at', 'completed_at'])
                ->map(fn (ProjectMilestone $m) => $this->milestone($m, null)));
        }

        return $items->values();
    }

    /**
     * Running and planned projects for the timeline, with progress.
     *
     * @return Collection<int, Project>
     */
    public function projectsForUser(User $user, School $school, string $mode, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($this->features->disabled(Feature::Projects, $school)) {
            return collect();
        }

        return $this->timeline(Project::query()->inSchools([$school->id])
            ->when($mode === 'personal', fn ($q) => $q->involving($user), fn ($q) => $q->visibleTo($user, $user->can(Permission::ProjectsManage->value) ? $school->id : null)), $from, $to);
    }

    /** @return Collection<int, Project> */
    public function publicProjects(?int $schoolId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->timeline(Project::query()->publiclyVisible()->with('school:id,short_name,color,logo_media_id')
            ->when($schoolId, fn ($q) => $q->where('projects.school_id', $schoolId)), $from, $to);
    }

    /**
     * Items of each day of the period, sorted, keyed by Y-m-d.
     *
     * @param  Collection<int, CalendarItem>  $items
     * @return array<string, Collection<int, CalendarItem>>
     */
    public static function byDay(Collection $items, CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        for ($d = $from->toImmutable()->startOfDay(); $d->lessThanOrEqualTo($to); $d = $d->addDay()) {
            $day = $d;
            $list = $items->filter(fn (CalendarItem $i) => $i->covers($day))->sortBy(fn (CalendarItem $i) => $i->sortKey($day))->values();
            if ($list->isNotEmpty()) {
                $days[$d->toDateString()] = $list;
            }
        }

        return $days;
    }

    /** Monday-first weeks covering the month: 5 or 6 rows of 7 days. */
    public static function monthGrid(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
        $weeks = [];
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addWeek()) {
            $weeks[] = collect(range(0, 6))->map(fn ($i) => $d->addDays($i))->all();
        }

        return $weeks;
    }

    /** Parses "2026-12" into the first day of that month (current month when invalid). */
    public static function month(?string $value): CarbonImmutable
    {
        if ($value && preg_match('/^(\d{4})-(\d{2})$/', $value, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1)->startOfDay();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    private function overlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query->where('events.starts_at', '<=', $to)
            ->where(fn ($q) => $q->where('events.starts_at', '>=', $from)->orWhere('events.ends_at', '>=', $from))
            ->orderBy('events.starts_at');
    }

    private function event(Event $e, string $url, ?string $school = null): CalendarItem
    {
        $tone = match ($e->visibility) {
            ContentVisibility::Public => 'primary',
            ContentVisibility::InterSchool => 'violet',
            default => 'sky',
        };

        return new CalendarItem(
            kind: 'event', title: $e->title, start: $e->starts_at->toImmutable(), end: $e->ends_at?->toImmutable(),
            allDay: (bool) $e->all_day, url: $url, icon: $e->category->icon(), tone: $tone,
            meta: collect([$e->location, $school])->filter()->implode(' · ') ?: null,
        );
    }

    private function milestone(ProjectMilestone $m, ?string $url): CalendarItem
    {
        return new CalendarItem(
            kind: 'milestone', title: $m->title, start: $m->due_at->toImmutable(), allDay: $m->due_at->format('H:i') === '23:59', url: $url,
            icon: 'flag', tone: $m->completed_at ? 'emerald' : ($m->due_at->isPast() ? 'red' : 'emerald'),
            meta: $m->project?->name, done: $m->completed_at !== null, alert: ! $m->completed_at && $m->due_at->isPast(),
        );
    }

    /** @return Collection<int, Project> */
    private function timeline(Builder $query, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $query->overlapping($from, $to)
            ->whereIn('projects.status', [ProjectStatus::Planned->value, ProjectStatus::InProgress->value, ProjectStatus::OnHold->value])
            ->with(['milestones:id,project_id,title,due_at,completed_at'])
            ->orderByRaw('case when projects.starts_on is null then 1 else 0 end')->orderBy('projects.starts_on')->orderBy('projects.name')
            ->limit(30)
            ->get();
    }
}
