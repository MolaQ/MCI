<?php

namespace App\Livewire\Admin\Users;

use App\Domain\Users\Actions\SaveUser;
use App\Domain\Users\Actions\SendAccountSetupLink;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\User;
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
        abort_unless(in_array($column, ['last_name', 'email', 'status', 'last_seen_at'], true), 400);

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
        return User::query()
            ->with(['roles', 'groups'])
            ->search($this->search)
            ->when($this->role !== '', fn ($q) => $q->role($this->role))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->group !== '', fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey((int) $this->group)))
            ->when($this->withoutGroups, fn ($q) => $q->whereDoesntHave('groups'))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->when($this->sortBy === 'last_name', fn ($q) => $q->orderBy('first_name', $this->sortDirection))
            ->paginate(25);
    }

    #[Computed]
    public function groups()
    {
        return Group::query()->orderBy('name')->get(['id', 'name']);
    }

    public function sendSetupLink(int $userId, SendAccountSetupLink $action): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('update', $user);

        $action->handle($user);

        Flux::toast(variant: 'success', text: __('Wysłano link do ustawienia hasła na adres :email.', ['email' => $user->email]));
    }

    public function toggleSuspension(int $userId, SaveUser $save): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('suspend', $user);

        $suspend = $user->status !== UserStatus::Suspended;
        $save->handle(['status' => $suspend ? UserStatus::Suspended : ($user->password ? UserStatus::Active : UserStatus::Invited)], $user);

        Flux::toast(text: $suspend ? __('Konto zawieszone.') : __('Konto przywrócone.'));
    }

    public function render()
    {
        $this->authorize('viewAny', User::class);

        return view('livewire.admin.users.index', [
            'roles' => Role::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }
}
