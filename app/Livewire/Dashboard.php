<?php

namespace App\Livewire;

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
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Signed-in home: the internal content of the current school comes first
 * (announcements, tasks, workflow decisions, events), each block only when
 * its module is switched on; school administration figures come last.
 */
#[Title('Pulpit')]
class Dashboard extends Component
{
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
