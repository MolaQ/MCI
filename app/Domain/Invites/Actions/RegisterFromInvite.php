<?php

namespace App\Domain\Invites\Actions;

use App\Enums\InviteKind;
use App\Enums\UserStatus;
use App\Models\Invite;
use App\Models\User;
use App\Notifications\VerifySignupNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Account creation from an invite (guest flow).
 *
 *  - Personal invite: the link was e-mailed to the address, which proves
 *    ownership → the account is active immediately.
 *  - Group link (QR poster): only if the invite and the school allow
 *    self-registration and the address is in the school's domains; the
 *    account stays "invited" until the person clicks the signed e-mail
 *    confirmation link. Memberships are prepared right away.
 */
class RegisterFromInvite
{
    public function __construct(private RedeemInvite $redeem) {}

    /**
     * @param  array{first_name: string, last_name: string, email?: string, password: string}  $data
     */
    public function handle(Invite $invite, array $data, ?string $ip = null, ?string $userAgent = null): User
    {
        $personal = $invite->kind === InviteKind::Personal;
        $email = $personal ? $invite->email : mb_strtolower(trim((string) ($data['email'] ?? '')));

        if (! $personal) {
            if (! $invite->allow_self_registration || ! $invite->school->allowsSelfRegistration()) {
                throw ValidationException::withMessages(['email' => __('To zaproszenie nie pozwala na zakładanie nowych kont. Zaloguj się istniejącym kontem.')]);
            }
            if (! $invite->school->acceptsEmailDomain($email)) {
                throw ValidationException::withMessages(['email' => __('Użyj adresu e-mail w domenie szkoły: :domains', ['domains' => implode(', ', $invite->school->email_domains ?? [])])]);
            }
        }

        $existing = User::withTrashed()->where('email', $email)->first();

        // A provisioned account (created by an admin, no password yet) may be
        // activated through a personal invite; anything else must log in.
        if ($existing && ! ($personal && $existing->status === UserStatus::Invited && $existing->password === null && ! $existing->trashed())) {
            throw ValidationException::withMessages(['email' => __('Konto z tym adresem już istnieje. Zaloguj się, aby przyjąć zaproszenie.')]);
        }

        return DB::transaction(function () use ($invite, $data, $email, $personal, $existing, $ip, $userAgent) {
            $user = $existing ?? new User;
            $user->fill([
                'first_name' => $existing?->first_name ?: trim($data['first_name']),
                'last_name' => $existing?->last_name ?: trim($data['last_name']),
                'email' => $email,
                'password' => $data['password'],
            ]);
            $user->status = $personal ? UserStatus::Active : UserStatus::Invited;
            $user->email_verified_at = $personal ? now() : null;
            $user->save();

            $this->redeem->handle($invite, $user, $ip, $userAgent, createdAccount: $existing === null);

            Audit::log('invite.signup', $user, ['invite_id' => $invite->id, 'kind' => $invite->kind->value], $user, $invite->school_id);

            if (! $personal) {
                $user->notify(new VerifySignupNotification($invite->school));
            }

            return $user;
        });
    }
}
