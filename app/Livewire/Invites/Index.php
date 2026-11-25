<?php

namespace App\Livewire\Invites;

use App\Enums\InviteKind;
use App\Enums\InviteStatus;
use App\Enums\Permission;
use App\Models\Invite;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Zaproszenia')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $kind = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Invite::class);
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function setKind(string $kind): void
    {
        $this->kind = $this->kind === $kind ? '' : $kind;
        $this->resetPage();
    }

    #[Computed]
    public function invites()
    {
        $user = Auth::user();

        return Invite::query()
            ->where('school_id', app(CurrentSchool::class)->id())
            ->unless($user->can(Permission::InvitesCreate->value), fn ($q) => $q->where('created_by', $user->id))
            ->when($this->status !== 'all', fn ($q) => $q->withStatus(InviteStatus::from($this->status)))
            ->when($this->kind !== '', fn ($q) => $q->where('kind', $this->kind))
            ->with(['groups', 'creator'])
            ->latest('id')
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.invites.index', [
            'statuses' => InviteStatus::cases(),
            'kinds' => InviteKind::cases(),
        ]);
    }
}
