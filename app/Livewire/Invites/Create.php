<?php

namespace App\Livewire\Invites;

use App\Domain\Invites\Actions\CreateInvite;
use App\Enums\GroupRole;
use App\Enums\InviteKind;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Invite;
use App\Support\CurrentSchool;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Nowe zaproszenie')]
class Create extends Component
{
    public string $kind = 'group_link';

    public string $label = '';

    public string $email = '';

    public string $role_name = '';

    /** @var array<int|string, string> group_id => member|leader */
    public array $groups = [];

    public ?int $max_uses = null;

    public int $days = 14;

    public bool $allow_self_registration = false;

    public bool $send_email = true;

    #[Url(as: 'grupa')]
    public ?int $preselect = null;

    public const DAY_PRESETS = [1, 7, 14, 30, 90, 180];

    public const USE_PRESETS = [1, 10, 30, 100];

    public function mount(): void
    {
        $this->authorize('create', Invite::class);
        $this->days = (int) config('mci.invites.default_days');

        if ($this->preselect && $this->invitableGroups->contains('id', $this->preselect)) {
            $this->groups[$this->preselect] = GroupRole::Member->value;
        }
    }

    #[Computed]
    public function invitableGroups()
    {
        $user = Auth::user();

        return Group::query()->inCurrentSchool()->orderBy('type')->orderBy('name')->get()
            ->filter(fn (Group $g) => Gate::allows('invite', $g))
            ->values()
            ->each(fn (Group $g) => $g->setAttribute('can_lead', $user->can('manageLeaders', $g)));
    }

    #[Computed]
    public function canGrantRoles(): bool
    {
        return Auth::user()->can(Permission::RolesManage->value);
    }

    public function toggleGroup(int $groupId): void
    {
        if (isset($this->groups[$groupId])) {
            unset($this->groups[$groupId]);
        } elseif ($this->invitableGroups->contains('id', $groupId)) {
            $this->groups[$groupId] = GroupRole::Member->value;
        }
    }

    public function toggleLeader(int $groupId): void
    {
        if (isset($this->groups[$groupId])) {
            $this->groups[$groupId] = $this->groups[$groupId] === GroupRole::Leader->value ? GroupRole::Member->value : GroupRole::Leader->value;
        }
    }

    protected function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(InviteKind::class)],
            'label' => ['nullable', 'string', 'max:150'],
            'email' => [Rule::requiredIf($this->kind === InviteKind::Personal->value), 'nullable', 'email', 'max:191'],
            'role_name' => ['nullable', Rule::enum(Role::class)],
            'groups' => ['array'],
            'groups.*' => [Rule::enum(GroupRole::class)],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'days' => ['required', 'integer', 'min:1', 'max:'.config('mci.invites.max_days')],
        ];
    }

    public function save(CreateInvite $create)
    {
        $this->authorize('create', Invite::class);
        $data = $this->validate();

        [$invite] = $create->handle(Auth::user(), app(CurrentSchool::class)->required(), [
            'kind' => $data['kind'],
            'label' => $data['label'],
            'email' => $data['email'],
            'role_name' => $this->canGrantRoles ? ($data['role_name'] ?: null) : null,
            'groups' => $this->groups,
            'max_uses' => $data['max_uses'],
            'expires_at' => now()->addDays($data['days'])->endOfDay(),
            'allow_self_registration' => $this->allow_self_registration,
            'send_email' => $this->send_email,
        ]);

        Flux::toast(variant: 'success', text: $invite->kind === InviteKind::Personal && $this->send_email
            ? __('Zaproszenie wysłane na :email.', ['email' => $invite->email])
            : __('Zaproszenie utworzone.'));

        return $this->redirectRoute('invites.show', $invite, navigate: true);
    }

    public function render()
    {
        return view('livewire.invites.create', ['roles' => Role::cases()]);
    }
}
