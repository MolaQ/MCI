<?php

use App\Enums\UserStatus;
use App\Models\User;

it('shows the Polish login page', function () {
    $this->get('/login')->assertOk()->assertSee('Zaloguj się');
});

it('redirects guests from the app to the login page, but shows them the public front', function () {
    $this->get(route('dashboard'))->assertRedirect('/login');
    $this->get('/')->assertOk()->assertSee('Zaloguj się');
});

it('logs in an active user', function () {
    $user = User::factory()->create(['email' => 'anna@szkola.edu.pl']);

    $this->post('/login', ['email' => 'ANNA@szkola.edu.pl ', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_seen_at)->not->toBeNull();
});

it('rejects a wrong password', function () {
    User::factory()->create(['email' => 'anna@szkola.edu.pl']);

    $this->post('/login', ['email' => 'anna@szkola.edu.pl', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('blocks suspended accounts', function () {
    User::factory()->suspended()->create(['email' => 'anna@szkola.edu.pl']);

    $this->post('/login', ['email' => 'anna@szkola.edu.pl', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('blocks invited accounts that have no password yet', function () {
    User::factory()->invited()->create(['email' => 'anna@szkola.edu.pl']);

    $this->post('/login', ['email' => 'anna@szkola.edu.pl', 'password' => ''])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

it('logs out a user who is suspended during an open session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/')->assertOk();

    $user->update(['status' => UserStatus::Suspended]);

    $this->actingAs($user->fresh())->get('/')->assertRedirect('/login');
    $this->assertGuest();
});

it('has no public registration', function () {
    $this->get('/register')->assertNotFound();
});
