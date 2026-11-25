<?php

use App\Domain\Invites\Actions\CreateInvite;
use App\Domain\Invites\Actions\RedeemInvite;
use App\Enums\GroupRole;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use App\Notifications\VerifySignupNotification;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->math = Group::factory()->create(['name' => 'Matematyka']);
    $this->tutors = Group::factory()->create(['name' => 'Wychowawcy']);
    $this->volunteers = Group::factory()->create(['name' => 'Wolontariat']);
    $this->creator = admin();
});

function invite(array $data = []): array
{
    return app(CreateInvite::class)->handle(test()->creator, app(CurrentSchool::class)->get(), $data + [
        'kind' => 'group_link',
        'groups' => [test()->math->id => 'member', test()->tutors->id => 'leader', test()->volunteers->id => 'member'],
    ]);
}

it('lets a signed-in teacher from another school join the school and three groups in one step', function () {
    [$invite, $token] = invite(['role_name' => 'teacher']);
    $other = School::factory()->create();
    $teacher = userWithRole(Role::Teacher, school: $other);

    $this->actingAs($teacher)->get(route('join.show', $token))
        ->assertOk()->assertSee('Matematyka')->assertSee('Wychowawcy')->assertSee('Dołącz');

    $this->actingAs($teacher)->post(route('join.accept', $token))->assertRedirect(route('dashboard'));

    $teacher->refresh();
    actAsSchool($this->school, $teacher);
    expect($teacher->belongsToSchool($this->school))->toBeTrue()
        ->and($teacher->belongsToSchool($other))->toBeTrue()
        ->and($teacher->schoolGroups()->count())->toBe(3)
        ->and($teacher->leadsGroup($this->tutors))->toBeTrue()
        ->and($teacher->hasRole('teacher'))->toBeTrue()
        ->and($invite->fresh()->uses_count)->toBe(1)
        ->and(session(CurrentSchool::SESSION_KEY))->toBe($this->school->id);
});

it('never downgrades a leader and does not count a second use by the same person', function () {
    [$invite, $token] = invite(['groups' => [$this->math->id => 'member']]);
    $user = member();
    $this->math->members()->attach($user->id, ['role' => GroupRole::Leader->value]);

    app(RedeemInvite::class)->handle($invite, $user);
    app(RedeemInvite::class)->handle($invite->fresh(), $user);

    expect($user->leadsGroup($this->math))->toBeTrue()
        ->and($invite->fresh()->uses_count)->toBe(1);
});

it('shows the same neutral 404 for unknown, expired, revoked and used-up invites', function () {
    [$expired, $t1] = invite();
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();
    [$revoked, $t2] = invite();
    $revoked->forceFill(['revoked_at' => now()])->save();
    [$used, $t3] = invite(['max_uses' => 1]);
    $used->forceFill(['uses_count' => 1])->save();

    foreach ([$t1, $t2, $t3, str_repeat('a', 40), 'x', '../../etc/passwd'] as $token) {
        $this->get('/dolacz/'.rawurlencode($token))->assertNotFound()->assertSee('Zaproszenie jest nieważne lub wygasło');
    }
});

it('stops working when the school is deactivated', function () {
    [, $token] = invite();
    $this->school->update(['is_active' => false]);

    $this->get(route('join.show', $token))->assertNotFound();
});

it('enforces max_uses', function () {
    [$invite, $token] = invite(['max_uses' => 1]);
    $a = member([], School::factory()->create());
    $b = member([], School::factory()->create());

    $this->actingAs($a)->post(route('join.accept', $token))->assertRedirect(route('dashboard'));
    $this->actingAs($b)->post(route('join.accept', $token))->assertNotFound();

    expect($invite->fresh()->uses_count)->toBe(1)
        ->and($b->belongsToSchool($this->school))->toBeFalse();
});

it('refuses people whose access to the school is suspended', function () {
    [, $token] = invite();
    $user = member();
    $user->schools()->updateExistingPivot($this->school->id, ['status' => 'suspended']);

    $this->actingAs($user)->post(route('join.accept', $token))->assertSessionHasErrors('invite');
    expect($user->groups()->count())->toBe(0);
});

it('binds personal invites to their e-mail address', function () {
    [, $token] = invite(['kind' => 'personal', 'email' => 'anna@szkola.edu.pl']);
    $intruder = member(['email' => 'ktos@szkola.edu.pl']);

    $this->actingAs($intruder)->get(route('join.show', $token))->assertSee('przypisane do innego adresu');
    $this->actingAs($intruder)->post(route('join.accept', $token))->assertSessionHasErrors('invite');
    expect($intruder->groups()->count())->toBe(0);
});

it('creates an active account from a personal invite and logs the person in', function () {
    [$invite, $token] = invite(['kind' => 'personal', 'email' => 'anna@szkola.edu.pl']);

    $this->get(route('join.show', $token))->assertOk()->assertSee('Utwórz konto i dołącz');

    $this->post(route('join.register', $token), [
        'first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'podmieniony@zly.pl',
        'password' => 'HasloTestowe123', 'password_confirmation' => 'HasloTestowe123', 'accept' => '1',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'anna@szkola.edu.pl')->sole();
    expect($user->status)->toBe(UserStatus::Active)
        ->and(User::query()->where('email', 'podmieniony@zly.pl')->exists())->toBeFalse()
        ->and($user->schoolGroups()->count())->toBe(3)
        ->and($invite->fresh()->uses_count)->toBe(1);
    $this->assertAuthenticatedAs($user);
});

it('activates an account an admin already created through a personal invite', function () {
    $provisioned = User::factory()->invited()->create(['email' => 'jan@szkola.edu.pl']);
    [, $token] = invite(['kind' => 'personal', 'email' => 'jan@szkola.edu.pl']);

    $this->get(route('join.show', $token))->assertSee('czeka na aktywację');
    $this->post(route('join.activate', $token), ['password' => 'HasloTestowe123', 'password_confirmation' => 'HasloTestowe123'])
        ->assertRedirect(route('dashboard'));

    expect($provisioned->fresh()->status)->toBe(UserStatus::Active)
        ->and($provisioned->fresh()->belongsToSchool($this->school))->toBeTrue();
});

it('lets a newcomer self-register from a QR code only with a school e-mail and confirmation', function () {
    $this->school->update(['email_domains' => ['zsm.edu.pl']]);
    [$invite, $token] = invite(['allow_self_registration' => true]);

    $payload = ['first_name' => 'Ola', 'last_name' => 'Mak', 'password' => 'HasloTestowe123', 'password_confirmation' => 'HasloTestowe123', 'accept' => '1'];

    $this->post(route('join.register', $token), $payload + ['email' => 'ola@gmail.com'])->assertSessionHasErrors('email');
    expect(User::query()->where('email', 'ola@gmail.com')->exists())->toBeFalse();

    $this->post(route('join.register', $token), $payload + ['email' => 'ola@zsm.edu.pl'])->assertRedirect(route('join.pending'));

    $user = User::query()->where('email', 'ola@zsm.edu.pl')->sole();
    expect($user->status)->toBe(UserStatus::Invited)
        ->and($user->schoolGroups()->count())->toBe(3);
    $this->assertGuest();

    // Cannot log in before confirming the address.
    $this->post('/login', ['email' => 'ola@zsm.edu.pl', 'password' => 'HasloTestowe123'])->assertSessionHasErrors('email');
    $this->assertGuest();

    Notification::assertSentTo($user, VerifySignupNotification::class);
    $this->get(VerifySignupNotification::verificationUrl($user))->assertRedirect(route('login'));
    expect($user->fresh()->status)->toBe(UserStatus::Active);

    $this->post('/login', ['email' => 'ola@zsm.edu.pl', 'password' => 'HasloTestowe123']);
    $this->assertAuthenticatedAs($user->fresh());
});

it('rejects tampered or unsigned verification links', function () {
    $user = User::factory()->invited()->create(['password' => 'x']);

    $this->get(route('join.verify', ['user' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();
    expect($user->fresh()->status)->toBe(UserStatus::Invited);
});

it('does not allow self-registration unless the invite and the school allow it', function () {
    [, $token] = invite(); // allow_self_registration = false
    $payload = ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.pl', 'password' => 'HasloTestowe123', 'password_confirmation' => 'HasloTestowe123', 'accept' => '1'];

    $this->post(route('join.register', $token), $payload)->assertSessionHasErrors('email');

    [, $token2] = invite(['allow_self_registration' => true]);
    $this->school->update(['settings' => ['allow_self_registration' => false]]);
    $this->post(route('join.register', $token2), $payload)->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'a@b.pl')->exists())->toBeFalse();
});

it('does not let a QR sign-up take over an existing account', function () {
    member(['email' => 'zajety@zsm.edu.pl']);
    [, $token] = invite(['allow_self_registration' => true]);

    $this->post(route('join.register', $token), [
        'first_name' => 'A', 'last_name' => 'B', 'email' => 'zajety@zsm.edu.pl',
        'password' => 'HasloTestowe123', 'password_confirmation' => 'HasloTestowe123', 'accept' => '1',
    ])->assertSessionHasErrors('email');
});

it('sends guests with an account to the login page and back to the invite', function () {
    [, $token] = invite();

    $this->post(route('join.login', $token))->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('join.show', $token));
});

it('rate-limits the public join endpoints', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->get('/dolacz/'.str_repeat('b', 40));
    }

    $this->get('/dolacz/'.str_repeat('b', 40))->assertStatus(429);
});
