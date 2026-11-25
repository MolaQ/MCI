<?php

namespace App\Livewire\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mój profil')]
class Profile extends Component
{
    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->phone = (string) Auth::user()->phone;
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
        return view('livewire.settings.profile', [
            'user' => Auth::user()->load('groups', 'roles'),
        ]);
    }
}
