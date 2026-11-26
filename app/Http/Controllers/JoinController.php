<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Domain\Invites\Actions\RedeemInvite;
use App\Domain\Invites\Actions\RegisterFromInvite;
use App\Enums\Feature;
use App\Enums\InviteKind;
use App\Enums\UserStatus;
use App\Models\Invite;
use App\Models\User;
use App\Support\Appearance;
use App\Support\Audit;
use App\Support\CurrentSchool;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Public entry point for invite links and QR codes: /dolacz/{token}.
 *
 * Every unusable token (unknown, expired, revoked, used up, inactive school)
 * renders the same neutral page with HTTP 404, so links cannot be probed.
 */
class JoinController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): View|Response
    {
        $invite = $this->usableInvite($token);
        $user = $request->user();

        if (! $invite) {
            return $this->invalid();
        }

        $existing = $invite->kind === InviteKind::Personal
            ? User::query()->where('email', $invite->email)->first()
            : null;

        return view('join.show', [
            'invite' => $invite,
            'token' => $token,
            'user' => $user,
            'emailMismatch' => $user && $invite->kind === InviteKind::Personal && mb_strtolower($user->email) !== $invite->email,
            'needsActivation' => ! $user && $existing && $existing->status === UserStatus::Invited && $existing->password === null,
            'accountExists' => ! $user && $existing && $existing->password !== null,
            'canRegister' => ! $user && (
                ($invite->kind === InviteKind::Personal && ! $existing)
                || ($invite->kind === InviteKind::GroupLink && $invite->allow_self_registration && $invite->school->allowsSelfRegistration())
            ),
            'alreadyMember' => $user && $user->belongsToSchool($invite->school)
                && $invite->groups->every(fn ($g) => $user->groups()->whereKey($g->id)->exists()),
        ]);
    }

    public function login(Request $request, string $token): RedirectResponse
    {
        // Return to this invite after signing in.
        $request->session()->put('url.intended', route('join.show', $token));

        return redirect()->route('login');
    }

    public function accept(Request $request, string $token, RedeemInvite $redeem): RedirectResponse|Response
    {
        $invite = $this->usableInvite($token, allowRedeemedBy: $request->user());

        if (! $invite) {
            return $this->invalid();
        }

        try {
            $result = $redeem->handle($invite, $request->user(), $request->ip(), $request->userAgent());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $this->switchTo($request, $invite);

        return redirect()->route('dashboard')->with('status', $result['groups']
            ? __('Dołączono do :school: :groups.', ['school' => $invite->school->short_name, 'groups' => implode(', ', $result['groups'])])
            : __('Należysz już do :school i wskazanych grup.', ['school' => $invite->school->short_name]));
    }

    public function register(Request $request, string $token, RegisterFromInvite $register): RedirectResponse|Response
    {
        $invite = $this->usableInvite($token);

        if (! $invite) {
            return $this->invalid();
        }

        $personal = $invite->kind === InviteKind::Personal;
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => $personal ? ['nullable'] : ['required', 'email', 'max:191'],
            'password' => $this->passwordRules(),
            'accept' => ['accepted'],
        ], [], ['first_name' => __('imię'), 'last_name' => __('nazwisko'), 'accept' => __('zgoda')]);

        $user = $register->handle($invite, $data, $request->ip(), $request->userAgent());

        if ($personal) {
            Auth::login($user);
            $request->session()->regenerate();
            $this->switchTo($request, $invite);

            return redirect()->route('dashboard')->with('status', __('Witaj w :app! Konto jest aktywne.', ['app' => app(Appearance::class)->shortName()]));
        }

        return redirect()->route('join.pending')->with('email', $user->email);
    }

    /**
     * Personal invite for an account an admin already created (no password yet).
     */
    public function activate(Request $request, string $token, RegisterFromInvite $register): RedirectResponse|Response
    {
        $invite = $this->usableInvite($token);

        if (! $invite || $invite->kind !== InviteKind::Personal) {
            return $this->invalid();
        }

        $data = $request->validate(['password' => $this->passwordRules()]);
        $user = $register->handle($invite, $data + ['first_name' => '', 'last_name' => ''], $request->ip(), $request->userAgent());

        Auth::login($user);
        $request->session()->regenerate();
        $this->switchTo($request, $invite);

        return redirect()->route('dashboard')->with('status', __('Hasło ustawione. Witaj w :app!', ['app' => app(Appearance::class)->shortName()]));
    }

    public function pending(): View
    {
        return view('join.pending');
    }

    public function verify(Request $request, User $user, string $hash): RedirectResponse
    {
        abort_unless(hash_equals(sha1($user->email), $hash), 403);

        if ($user->status === UserStatus::Invited && $user->email_verified_at === null) {
            $user->forceFill(['status' => UserStatus::Active, 'email_verified_at' => now()])->save();
            Audit::log('invite.email_verified', $user, actor: $user, schoolId: $user->schools()->value('schools.id'));
        }

        return redirect()->route('login')->with('status', __('Adres e-mail potwierdzony. Możesz się zalogować.'));
    }

    private function usableInvite(string $token, ?User $allowRedeemedBy = null): ?Invite
    {
        $invite = Invite::findByToken($token);

        // A school that switched invites off makes all its links neutral.
        if (! $invite || app(Features::class)->disabled(Feature::Invites, $invite->school_id)) {
            return null;
        }

        if ($invite->isUsable()) {
            return $invite;
        }

        // Re-opening an invite you already used (e.g. double click) is fine.
        if ($allowRedeemedBy && $invite->school?->is_active && $invite->revoked_at === null
            && $invite->redemptions()->where('user_id', $allowRedeemedBy->id)->exists()) {
            return $invite;
        }

        return null;
    }

    private function invalid(): Response
    {
        return response()->view('join.invalid', [], 404);
    }

    private function switchTo(Request $request, Invite $invite): void
    {
        $request->session()->put(CurrentSchool::SESSION_KEY, $invite->school_id);
        $request->user()?->forceFill(['last_school_id' => $invite->school_id])->saveQuietly();
    }
}
