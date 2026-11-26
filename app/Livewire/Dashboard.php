<?php

namespace App\Livewire;

use App\Domain\Calendar\Agenda;
use App\Enums\AnnouncementPriority;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Enums\WorkflowStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Support\CurrentSchool;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Signed-in home, a command centre for "what is happening at school":
 * 1. today's agenda (events, my deadlines, key announcements) next to what
 *    needs my attention, 2. the month calendar (personal or school view,
 *    default per person → school → site), 3. projects stretched over time,
 * then my requests, groups and the school administration figures.
 * Every block follows its module switch.
 */
#[Title('Pulpit')]
class Dashboard extends Component
{
    #[Url(as: 'miesiac', except: '')]
    public string $month = '';

    #[Url(as: 'kalendarz', except: '')]
    public string $calendar = '';

    public function mount(): void
    {
        if (! in_array($this->calendar, Agenda::MODES, true)) {
            $this->calendar = '';
        }
    }

    /** The view shown now: chosen in the toggle, else the person's default. */
    #[Computed]
    public function mode(): string
    {
        return $this->calendar !== '' ? $this->calendar : $this->defaultMode;
    }

    #[Computed]
    public function defaultMode(): string
    {
        return Agenda::defaultMode(Auth::user(), app(CurrentSchool::class)->get());
    }

    public function setCalendar(string $mode): void
    {
        $this->calendar = in_array($mode, Agenda::MODES, true) && $mode !== $this->defaultMode ? $mode : '';
        unset($this->mode, $this->monthDays, $this->timeline);
    }

    /** Makes the current view the person's default (also in Profile). */
    public function rememberCalendar(): void
    {
        $user = Auth::user();
        $prefs = $user->notification_prefs ?? [];
        $prefs['calendar'] = $this->mode;
        $user->forceFill(['notification_prefs' => $prefs])->save();
        $this->calendar = '';
        unset($this->defaultMode, $this->mode);
        Flux::toast(text: __('Zapisano domyślny widok kalendarza.'));
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $by === 0 ? '' : Agenda::month($this->month)->addMonths(max(-12, min(12, $by)))->format('Y-m');
        if ($this->month === now()->format('Y-m')) {
            $this->month = '';
        }
        unset($this->monthDays);
    }

    #[Computed]
    public function currentMonth(): CarbonImmutable
    {
        return Agenda::month($this->month);
    }

    /** Today's items in the current school (always the personal view: what concerns me today). */
    #[Computed]
    public function today(): Collection
    {
        $school = app(CurrentSchool::class)->get();
        if (! $school) {
            return collect();
        }
        $items = app(Agenda::class)->forUser(Auth::user(), $school, 'personal', now()->startOfDay(), now()->endOfDay());

        return collect(Agenda::byDay($items, now()->startOfDay(), now()->endOfDay())[now()->toDateString()] ?? []);
    }

    /** @return array<string, Collection> */
    #[Computed]
    public function monthDays(): array
    {
        $school = app(CurrentSchool::class)->get();
        if (! $school) {
            return [];
        }
        $grid = Agenda::monthGrid($this->currentMonth);
        $from = $grid[0][0]->startOfDay();
        $to = end($grid)[6]->endOfDay();

        return Agenda::byDay(app(Agenda::class)->forUser(Auth::user(), $school, $this->mode, $from, $to), $from, $to);
    }

    /** @return array{from: CarbonImmutable, to: CarbonImmutable} */
    #[Computed]
    public function window(): array
    {
        $from = CarbonImmutable::now()->startOfWeek()->subWeeks(2);

        return ['from' => $from, 'to' => $from->addWeeks(16)->endOfDay()];
    }

    #[Computed]
    public function timeline(): Collection
    {
        $school = app(CurrentSchool::class)->get();

        return $school ? app(Agenda::class)->projectsForUser(Auth::user(), $school, $this->mode, $this->window['from'], $this->window['to']) : collect();
    }

    /** Key announcements of the day: unread important/urgent ones, or published today. */
    #[Computed]
    public function keyAnnouncements(): Collection
    {
        if (! $this->on(Feature::Announcements)) {
            return collect();
        }

        return Auth::user()->announcements()->live()
            ->where('announcements.school_id', app(CurrentSchool::class)->id())
            ->where(fn ($q) => $q->where('announcements.published_at', '>=', now()->startOfDay())
                ->orWhere(fn ($u) => $u->whereNull('announcement_recipients.read_at')->whereIn('announcements.priority', [AnnouncementPriority::Important->value, AnnouncementPriority::Urgent->value])))
            ->orderByRaw('case announcements.priority when ? then 0 when ? then 1 else 2 end', [AnnouncementPriority::Urgent->value, AnnouncementPriority::Important->value])
            ->latest('published_at')->limit(3)->get();
    }

    private function on(Feature $feature): bool
    {
        return app(CurrentSchool::class)->id() !== null && app(Features::class)->enabled($feature);
    }

    /** @return array{unread: int, tasks: int, overdue: int, decisions: int, events: int} */
    #[Computed]
    public function summary(): array
    {
        $user = Auth::user();
        $schoolId = app(CurrentSchool::class)->id();
        $tasks = fn () => Task::query()->assignedTo($user)->open()->where('tasks.school_id', $schoolId);

        return [
            'unread' => $this->on(Feature::Announcements) ? $user->unreadAnnouncementsCount($schoolId) : 0,
            'tasks' => $this->on(Feature::Tasks) ? $tasks()->whereNotNull('tasks.due_at')->where('tasks.due_at', '<=', now()->endOfDay())->count() : 0,
            'overdue' => $this->on(Feature::Tasks) ? $tasks()->overdue()->count() : 0,
            'decisions' => $this->on(Feature::Workflows) ? WorkflowRequest::query()->awaitingDecisionBy($user)->count() : 0,
            'events' => $this->on(Feature::Events) ? $this->schoolEvents()->whereBetween('events.starts_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])->count() : 0,
        ];
    }

    #[Computed]
    public function latestAnnouncements()
    {
        return Auth::user()->announcements()->live()
            ->where('announcements.school_id', app(CurrentSchool::class)->id())
            ->with('author')
            ->orderByRaw('CASE WHEN announcement_recipients.read_at IS NULL THEN 0 ELSE 1 END')
            ->latest('published_at')
            ->limit(5)
            ->get();
    }

    /** Open tasks due this week or overdue (plus undated urgent ones). */
    #[Computed]
    public function myTasks()
    {
        return Task::query()->assignedTo(Auth::user())->open()
            ->where('tasks.school_id', app(CurrentSchool::class)->id())
            ->where(fn ($q) => $q->where('tasks.due_at', '<=', now()->endOfWeek())->orWhere(fn ($u) => $u->whereNull('tasks.due_at')->where('tasks.priority', '>=', 4)))
            ->orderByRaw('case when tasks.due_at is null then 1 else 0 end')->orderBy('tasks.due_at')->orderByDesc('tasks.priority')
            ->limit(6)->get();
    }

    /** Events of the current school this person may see (internal ones included). */
    private function schoolEvents()
    {
        return Event::query()->visibleTo(Auth::user())->where('events.school_id', app(CurrentSchool::class)->id());
    }

    #[Computed]
    public function upcomingEvents()
    {
        return $this->schoolEvents()->upcoming()
            ->with('school')
            ->orderBy('starts_at')
            ->limit(5)
            ->get();
    }

    /** Requests waiting for this person's decision, most urgent first. */
    #[Computed]
    public function decisions()
    {
        return WorkflowRequest::query()->awaitingDecisionBy(Auth::user())
            ->with(['requester:id,first_name,last_name', 'school:id,short_name'])
            ->orderByRaw('case when workflow_requests.stage_due_at is null then 1 else 0 end')
            ->orderBy('workflow_requests.stage_due_at')->latest('workflow_requests.submitted_at')
            ->limit(5)->get();
    }

    /** This person's own open requests in the current school. */
    #[Computed]
    public function myRequests()
    {
        return WorkflowRequest::query()->where('workflow_requests.requester_id', Auth::id())
            ->where('workflow_requests.school_id', app(CurrentSchool::class)->id())
            ->whereIn('workflow_requests.status', WorkflowStatus::openValues())
            ->latest('workflow_requests.submitted_at')
            ->limit(5)->get();
    }

    #[Computed]
    public function myGroups()
    {
        return Auth::user()->schoolGroups()->withCount('members')->orderBy('name')->get();
    }

    /**
     * @return array<string, int>|null
     */
    #[Computed]
    public function adminStats(): ?array
    {
        if (! app(CurrentSchool::class)->id() || ! Auth::user()->can(Permission::UsersManage->value)) {
            return null;
        }

        $schoolId = app(CurrentSchool::class)->id();
        $members = fn () => User::query()->join('school_user', 'school_user.user_id', '=', 'users.id')->where('school_user.school_id', $schoolId);

        return [
            'active' => $members()->where('school_user.status', 'active')->where('users.status', UserStatus::Active->value)->count(),
            'invited' => $members()->where('school_user.status', 'active')->where('users.status', UserStatus::Invited->value)->count(),
            'suspended' => $members()->where('school_user.status', 'suspended')->count(),
            'groups' => Group::query()->inCurrentSchool()->count(),
            'without_groups' => $members()->whereDoesntHave('groups', fn ($q) => $q->where('groups.school_id', $schoolId))->count(),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
