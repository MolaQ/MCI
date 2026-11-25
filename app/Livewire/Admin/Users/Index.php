<?php

namespace App\Livewire\Admin\Users;

use App\Domain\Schools\Actions\ManageSchoolMembership;
use App\Domain\Users\Actions\SendAccountSetupLink;
use App\Enums\Role;
use App\Enums\SchoolMemberStatus;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\User;
use App\Support\CurrentSchool;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Użytkownicy')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: false)]
    public bool $withoutGroups = false;

    public string $sortBy = 'last_name';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'status', 'group', 'withoutGroups'], true)) {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        abort_unless(in_array($column, ['last_name', 'email', 'last_seen_at'], true), 400);

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'role', 'status', 'group', 'withoutGroups');
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        $schoolId = app(CurrentSchool::class)->id();

        return User::query()
            ->join('school_user', fn ($j) => $j->on('school_user.user_id', '=', 'users.id')->where('school_user.school_id', $schoolId))
            ->select('users.*', 'school_user.status as school_status')
            ->with(['roles', 'groups' => fn ($q) => $q->where('groups.school_id', $schoolId)])
            ->search($this->search)
            ->when($this->role !== '', fn ($q) => $q->role($this->role))
            ->when($this->status === SchoolMemberStatus::Suspended->value, fn ($q) => $q->where('school_user.status', SchoolMemberStatus::Suspended->value))
            ->when(in_array($this->status, [UserStatus::Invited->value, UserStatus::Active->value], true), fn ($q) => $q
                ->where('school_user.status', SchoolMemberStatus::Active->value)
                ->where('users.status', $this->status))
            ->when($this->group !== '', fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey((int) $this->group)))
            ->when($this->withoutGroups, fn ($q) => $q->whereDoesntHave('groups', fn ($g) => $g->where('groups.school_id', $schoolId)))
            ->orderBy('users.'.$this->sortBy, $this->sortDirection)
            ->when($this->sortBy === 'last_name', fn ($q) => $q->orderBy('users.first_name', $this->sortDirection))
            ->paginate(25);
    }

    #[Computed]
    public function groups()
    {
        return Group::query()->inCurrentSchool()->orderBy('name')->get(['id', 'name']);
    }

    public function sendSetupLink(int $userId, SendAccountSetupLink $action): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('update', $user);

        $action->handle($user);

        Flux::toast(variant: 'success', text: __('Wysłano link do ustawienia hasła na adres :email.', ['email' => $user->email]));
    }

    /**
     * Suspends / restores the person's access to the active school only.
     */
    public function toggleSuspension(int $userId, ManageSchoolMembership $membership): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('suspend', $user);

        $school = app(CurrentSchool::class)->required();
        $suspend = $user->schoolStatus($school) !== SchoolMemberStatus::Suspended;
        $membership->setStatus($user, $school, $suspend ? SchoolMemberStatus::Suspended : SchoolMemberStatus::Active);

        Flux::toast(text: $suspend ? __('Dostęp do szkoły zawieszony.') : __('Dostęp do szkoły przywrócony.'));
    }

    public function render()
    {
        $this->authorize('viewAny', User::class);

        return view('livewire.admin.users.index', [
            'roles' => Role::cases(),
            'statuses' => [
                UserStatus::Active->value => __('Aktywne'),
                UserStatus::Invited->value => __('Oczekuje'),
                SchoolMemberStatus::Suspended->value => __('Zawieszone w szkole'),
            ],
        ]);
    }
}
