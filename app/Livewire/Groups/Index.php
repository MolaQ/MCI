<?php

namespace App\Livewire\Groups;

use App\Domain\Taxonomy\TermCatalog;
use App\Enums\MembershipMode;
use App\Enums\Permission;
use App\Enums\TermKind;
use App\Models\Group;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Grupy')]
class Index extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Computed]
    public function groups()
    {
        $user = Auth::user();

        return Group::query()->inCurrentSchool()
            ->withCount('members')
            ->with(['leaders:id,first_name,last_name'])
            ->unless($user->can(Permission::GroupsManage->value), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereHas('members', fn ($m) => $m->whereKey($user->id))
                ->orWhere('membership_mode', MembershipMode::Open->value)))
            ->search($this->search)
            ->when(ctype_digit($this->type), fn ($q) => $q->where('type_id', (int) $this->type))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function myGroupIds(): array
    {
        return Auth::user()->schoolGroups()->pluck('groups.id')->all();
    }

    public function render()
    {
        return view('livewire.groups.index', ['types' => app(TermCatalog::class)->forSchool(TermKind::GroupType, app(CurrentSchool::class)->get())]);
    }
}
