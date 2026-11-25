<?php

use App\Domain\Users\Actions\SendAccountSetupLink;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountSetupNotification;
use Illuminate\Support\Facades\Notification;

it('activates an invited account when the person sets a password from the e-mailed link', function () {
    Notification::fake();
    $this->actingAs(admin());

    $user = User::factory()->invited()->create(['email' => 'nowy@szkola.edu.pl']);
    app(SendAccountSetupLink::class)->handle($user);

    $token = null;
    Notification::assertSentTo($user, AccountSetupNotification::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    auth()->logout();

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()->assertSee('Ustaw hasło');

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NoweHaslo123',
        'password_confirmation' => 'NoweHaslo123',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull();

    $this->post('/login', ['email' => 'nowy@szkola.edu.pl', 'password' => 'NoweHaslo123']);
    $this->assertAuthenticatedAs($user);
});
