<?php

namespace App\Livewire\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Enums\ProfileVisibility;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mój profil')]
class Profile extends Component
{
    public string $phone = '';

    public string $profile_visibility = 'school';

    /** @var list<string> */
    public array $profile_fields = [];

    public const FIELDS = ['email', 'phone', 'groups'];

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->phone = (string) $user->phone;
        $this->profile_visibility = ($user->profile_visibility ?? ProfileVisibility::School)->value;
        $this->profile_fields = $user->profile_fields ?? ['groups'];
    }

    public function savePrivacy(): void
    {
        $this->validate([
            'profile_visibility' => ['required', Rule::enum(ProfileVisibility::class)],
            'profile_fields' => ['array'],
            'profile_fields.*' => [Rule::in(self::FIELDS)],
        ]);

        $user = Auth::user();
        $before = $user->profile_visibility?->value;
        $user->update(['profile_visibility' => $this->profile_visibility, 'profile_fields' => array_values($this->profile_fields)]);

        if ($before !== $this->profile_visibility) {
            Audit::log('user.profile_visibility_changed', $user, ['from' => $before, 'to' => $this->profile_visibility]);
        }

        Flux::toast(variant: 'success', text: __('Zapisano ustawienia prywatności.'));
    }

    public function saveProfile(): void
    {
        $this->validate(['phone' => ['nullable', 'string', 'max:32']]);
        Auth::user()->update(['phone' => $this->phone ?: null]);
        Flux::toast(variant: 'success', text: __('Zapisano.'));
    }

    public function updatePassword(UpdateUserPassword $action): void
    {
        try {
            $action->update(Auth::user(), [
                'current_password' => $this->current_password,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw ValidationException::withMessages($e->errors());
        }

        $this->reset('current_password', 'password', 'password_confirmation');
        Flux::toast(variant: 'success', text: __('Hasło zostało zmienione.'));
    }

    public function render()
    {
        $user = Auth::user()->load('roles', 'schools');

        return view('livewire.settings.profile', [
            'user' => $user,
            'groups' => $user->groups()->with('school')->orderBy('name')->get(),
            'visibilities' => ProfileVisibility::cases(),
            'effective' => $user->effectiveProfileVisibility(),
            'fields' => ['email' => __('E-mail'), 'phone' => __('Telefon'), 'groups' => __('Grupy')],
        ]);
    }
}
