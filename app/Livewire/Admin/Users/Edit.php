<?php

namespace App\Livewire\Admin\Users;

use App\Domain\Users\Actions\SaveUser;
use App\Domain\Users\Actions\SendAccountSetupLink;
use App\Enums\GroupRole;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\User;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Edit extends Component
{
    public ?User $user = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $status = 'invited';

    /** @var list<string> */
    public array $roles = ['teacher'];

    /** @var list<string> */
    public array $permissions = [];

    /** @var array<int|string, string> group_id => '' | member | leader */
    public array $memberships = [];

    public bool $sendSetupLink = true;

    public string $groupFilter = '';

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->authorize('update', $user);
            $this->user = $user;
            $this->fill($user->only(['first_name', 'last_name', 'email']));
            $this->phone = (string) $user->phone;
            $this->status = $user->status->value;
            $this->roles = $user->getRoleNames()->all();
            $this->permissions = $user->getDirectPermissions()->pluck('name')->all();
            $this->memberships = $user->groups->mapWithKeys(fn ($g) => [$g->id => $g->pivot->role->value])->all();
            $this->sendSetupLink = false;
        } else {
            $this->authorize('create', User::class);
        }
    }

    #[Computed]
    public function canManageAccess(): bool
    {
        return $this->user
            ? Auth::user()->can('manageAccess', $this->user)
            : Auth::user()->can(Permission::RolesManage->value);
    }

    #[Computed]
    public function groups()
    {
        return Group::query()->search($this->groupFilter)->orderBy('type')->orderBy('name')->get();
    }

    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'roles' => ['array', 'min:1'],
            'roles.*' => [Rule::enum(Role::class)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
            'memberships' => ['array'],
            'memberships.*' => ['nullable', Rule::in(['', ...array_column(GroupRole::cases(), 'value')])],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'first_name' => __('imię'),
            'last_name' => __('nazwisko'),
            'email' => __('e-mail'),
            'roles' => __('rola'),
        ];
    }

    public function save(SaveUser $save, SendAccountSetupLink $sendLink)
    {
        $this->authorize($this->user ? 'update' : 'create', $this->user ?? User::class);
        $this->email = mb_strtolower(trim($this->email));
        $data = $this->validate();

        if ($this->user?->is(Auth::user()) && $this->status === UserStatus::Suspended->value) {
            $this->addError('status', __('Nie możesz zawiesić własnego konta.'));

            return null;
        }

        $data['groups'] = collect($this->memberships)->filter()->all();

        if (! $this->canManageAccess) {
            unset($data['roles'], $data['permissions']);
        } elseif (in_array(Role::Admin->value, $data['roles'], true) && ! Auth::user()->isAdmin()) {
            $this->addError('roles', __('Tylko administrator może nadać rolę administratora.'));

            return null;
        }

        $creating = ! $this->user;
        $user = $save->handle($data, $this->user);

        if ($creating && $this->sendSetupLink) {
            $sendLink->handle($user);
        }

        Flux::toast(variant: 'success', text: $creating ? __('Utworzono konto.') : __('Zapisano zmiany.'));

        return $this->redirectRoute('admin.users.edit', $user, navigate: true);
    }

    public function delete()
    {
        $this->authorize('delete', $this->user);

        Audit::log('user.deleted', $this->user, ['email' => $this->user->email]);
        $this->user->groups()->detach();
        $this->user->delete();

        Flux::toast(text: __('Usunięto konto.'));

        return $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.users.edit', [
            'allRoles' => Role::cases(),
            'allPermissions' => Permission::cases(),
            'statuses' => UserStatus::cases(),
        ])->title($this->user ? $this->user->name : __('Nowy użytkownik'));
    }
}
