<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use App\Notifications\AccountSetupNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\Password;

/**
 * E-mails a "set your password" link to a provisioned account. Reuses the
 * password-broker tokens, so the link expires like a normal reset link.
 */
class SendAccountSetupLink
{
    public function handle(User $user): void
    {
        $token = Password::broker()->createToken($user);

        $user->notify(new AccountSetupNotification($token));

        Audit::log('user.setup_link_sent', $user);
    }
}
