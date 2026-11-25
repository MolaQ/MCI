<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::query()->where('email', mb_strtolower(trim((string) $request->input('email'))))->first();

            if (! $user || ! $user->password || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->status->canLogIn()) {
                throw ValidationException::withMessages([
                    Fortify::username() => $user->status === UserStatus::Invited
                        ? __('Konto nie jest jeszcze aktywne. Potwierdź adres e-mail linkiem z wiadomości.')
                        : __('Twoje konto zostało zawieszone. Skontaktuj się z administratorem.'),
                ]);
            }

            $user->forceFill(['last_seen_at' => now()])->save();

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
