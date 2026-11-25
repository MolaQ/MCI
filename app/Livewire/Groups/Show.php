<?php

namespace App\Livewire\Groups;

use App\Domain\Groups\Actions\AddGroupMember;
use App\Enums\GroupRole;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public Group $group;

    public string $search = '';

    public string $candidateSearch = '';

    public function mount(Group $group): void
    {
        $this->authorize('view', $group);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function members()
    {
        return $this->group->members()
            ->search($this->search)
            ->orderByRaw('CASE WHEN group_user.role = ? THEN 0 ELSE 1 END', [GroupRole::Leader->value])
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(30);
    }

    #[Computed]
    public function candidates()
    {
        if (mb_strlen(trim($this->candidateSearch)) < 2) {
            return collect();
        }

        return User::query()
            ->search($this->candidateSearch)
            ->where('status', '!=', UserStatus::Suspended->value)
            ->whereDoesntHave('groups', fn ($q) => $q->whereKey($this->group->id))
            ->orderBy('last_name')
            ->limit(8)
            ->get();
    }

    public function addMember(int $userId, AddGroupMember $action): void
    {
        $this->authorize('manageMembers', $this->group);

        $user = User::query()->findOrFail($userId);
        $action->handle($this->group, $user);

        $this->candidateSearch = '';
        unset($this->members, $this->candidates);
        Flux::toast(variant: 'success', text: __(':name dodano do grupy.', ['name' => $user->name]));
    }

    public function setRole(int $userId, string $role, AddGroupMember $action): void
    {
        $this->authorize('manageLeaders', $this->group);

        $action->changeRole($this->group, User::query()->findOrFail($userId), GroupRole::from($role));
        unset($this->members);
    }

    public function removeMember(int $userId, AddGroupMember $action): void
    {
        $this->authorize('manageMembers', $this->group);

        $user = User::query()->findOrFail($userId);
        if ($this->group->leaders()->whereKey($userId)->exists()) {
            $this->authorize('manageLeaders', $this->group);
        }

        $action->remove($this->group, $user);
        unset($this->members);
        Flux::toast(text: __(':name usunięto z grupy.', ['name' => $user->name]));
    }

    public function join(AddGroupMember $action): void
    {
        $this->authorize('join', $this->group);
        $action->handle($this->group, Auth::user(), joinedVia: 'self');
        unset($this->members);
        Flux::toast(variant: 'success', text: __('Dołączono do grupy.'));
    }

    public function leave(AddGroupMember $action): void
    {
        $this->authorize('leave', $this->group);
        $action->remove($this->group, Auth::user());
        unset($this->members);
        Flux::toast(text: __('Opuszczono grupę.'));
    }

    public function render()
    {
        // Re-checked on every request: losing access closes the page's data too.
        $this->authorize('view', $this->group);

        return view('livewire.groups.show')->title($this->group->name);
    }
}
