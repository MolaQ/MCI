<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\User;
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
        return Auth::user()->groups()->withCount('members')->orderBy('name')->get();
    }

    /**
     * @return array<string, int>|null
     */
    #[Computed]
    public function adminStats(): ?array
    {
        if (! Auth::user()->can(Permission::UsersManage->value)) {
            return null;
        }

        $byStatus = User::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'active' => (int) ($byStatus[UserStatus::Active->value] ?? 0),
            'invited' => (int) ($byStatus[UserStatus::Invited->value] ?? 0),
            'suspended' => (int) ($byStatus[UserStatus::Suspended->value] ?? 0),
            'groups' => Group::query()->count(),
            'without_groups' => User::query()->whereDoesntHave('groups')->count(),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
