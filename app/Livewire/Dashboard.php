<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Models\Group;
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
