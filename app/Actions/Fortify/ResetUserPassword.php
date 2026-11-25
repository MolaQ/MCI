<?php

namespace App\Actions\Fortify;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Used both for "forgot password" and for first-time account set-up
     * links, which is why an invited account becomes active here.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $activated = $user->status === UserStatus::Invited;

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'status' => $activated ? UserStatus::Active : $user->status,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($activated) {
            Audit::log('user.activated', $user, actor: $user);
        }
    }
}
