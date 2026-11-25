<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pulpit')]
class Dashboard extends Component
{
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

    #[Computed]
    public function upcomingEvents()
    {
        return Event::query()->visibleTo(Auth::user())->upcoming()
            ->with('school')
            ->orderBy('starts_at')
            ->limit(4)
            ->get();
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
