<?php

use App\Domain\Invites\Actions\CreateInvite;
use App\Enums\GroupRole;
use App\Enums\Role;
use App\Livewire\Invites\Create;
use App\Livewire\Invites\Index;
use App\Livewire\Invites\Show;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Invite;
use App\Models\School;
use App\Models\User;
use App\Notifications\InviteNotification;
use App\Support\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->math = Group::factory()->create(['name' => 'Matematyka']);
    $this->tutors = Group::factory()->create(['name' => 'Wychowawcy']);
    $this->sport = Group::factory()->create(['name' => 'Sport']);
});

function makeInvite(array $data = [], ?User $creator = null): array
{
    return app(CreateInvite::class)->handle($creator ?? admin(), app(CurrentSchool::class)->get(), $data + [
        'kind' => 'group_link',
        'groups' => [],
    ]);
}

it('creates a multi-group QR invite and stores only a hash of the token for lookups', function () {
    [$invite, $token] = makeInvite(['groups' => [$this->math->id => 'member', $this->tutors->id => 'leader', $this->sport->id => 'member']]);

    expect($invite->groups)->toHaveCount(3)
        ->and($invite->token_hash)->toBe(hash('sha256', $token))
        ->and(strlen($token))->toBe(40)
        ->and(Invite::findByToken($token)?->id)->toBe($invite->id)
        ->and(AuditLog::query()->where('action', 'invite.created')->where('school_id', $this->school->id)->exists())->toBeTrue();

    // The token column is encrypted at rest.
    $raw = DB::table('invites')->where('id', $invite->id)->value('token');
    expect($raw)->not->toBe($token)->not->toContain($token);
});

it('lets a group leader invite only to their own groups and never hand out leader seats', function () {
    $leader = userWithRole(Role::Teacher);
    $this->math->members()->attach($leader->id, ['role' => GroupRole::Leader->value]);

    [$invite] = makeInvite(['groups' => [$this->math->id => 'member']], $leader);
    expect($invite->exists)->toBeTrue();

    expect(fn () => makeInvite(['groups' => [$this->sport->id => 'member']], $leader))->toThrow(AuthorizationException::class);
    expect(fn () => makeInvite(['groups' => [$this->math->id => 'leader']], $leader))->toThrow(AuthorizationException::class);
});

it('refuses groups from another school', function () {
    $foreign = Group::factory()->create(['school_id' => School::factory()->create()->id]);

    expect(fn () => makeInvite(['groups' => [$foreign->id => 'member']]))->toThrow(ValidationException::class);
});

it('allows roles only with roles.manage, and the admin role only on personal invites', function () {
    $coordinator = userWithRole(Role::Coordinator);
    expect(fn () => makeInvite(['groups' => [$this->math->id => 'member'], 'role_name' => 'teacher'], $coordinator))
        ->toThrow(AuthorizationException::class);

    expect(fn () => makeInvite(['groups' => [$this->math->id => 'member'], 'role_name' => 'admin']))
        ->toThrow(ValidationException::class);

    Notification::fake();
    [$invite] = makeInvite(['kind' => 'personal', 'email' => 'dyrektor@szkola.edu.pl', 'role_name' => 'admin']);
    expect($invite->role_name)->toBe('admin')->and($invite->max_uses)->toBe(1);
});

it('caps the validity period', function () {
    expect(fn () => makeInvite(['groups' => [$this->math->id => 'member'], 'expires_at' => now()->addYears(3)]))
        ->toThrow(ValidationException::class);
});

it('e-mails personal invites with the link', function () {
    Notification::fake();
    [$invite, $token] = makeInvite(['kind' => 'personal', 'email' => 'Nowa@Szkola.edu.pl', 'groups' => [$this->math->id => 'member']]);

    expect($invite->email)->toBe('nowa@szkola.edu.pl');
    Notification::assertSentOnDemand(InviteNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'nowa@szkola.edu.pl' && $n->token === $token);
});

it('creates an invite from the Livewire screen with pill selectors', function () {
    $this->actingAs(userWithRole(Role::Coordinator));

    Livewire::test(Create::class)
        ->call('toggleGroup', $this->math->id)
        ->call('toggleGroup', $this->tutors->id)
        ->call('toggleLeader', $this->tutors->id)
        ->set('days', 30)
        ->set('max_uses', 50)
        ->set('allow_self_registration', true)
        ->call('save')
        ->assertHasNoErrors();

    $invite = Invite::query()->latest('id')->first();
    expect($invite->groups->pluck('pivot.group_role', 'id')->all())->toBe([$this->math->id => 'member', $this->tutors->id => 'leader'])
        ->and($invite->max_uses)->toBe(50)
        ->and($invite->allow_self_registration)->toBeTrue()
        ->and($invite->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

it('hides invite management from ordinary teachers', function () {
    $this->actingAs(userWithRole(Role::Teacher))->get(route('invites.index'))->assertForbidden();
});

it('shows a leader only the invites they created', function () {
    makeInvite(['groups' => [$this->sport->id => 'member'], 'label' => 'Cudze zaproszenie']);
    $leader = userWithRole(Role::Teacher);
    $this->math->members()->attach($leader->id, ['role' => 'leader']);
    makeInvite(['groups' => [$this->math->id => 'member'], 'label' => 'Moje zaproszenie'], $leader);

    $this->actingAs($leader);
    Livewire::test(Index::class)->assertSee('Moje zaproszenie')->assertDontSee('Cudze zaproszenie');
});

it('revokes an invite, shows the QR and serves a printable poster', function () {
    [$invite] = makeInvite(['groups' => [$this->math->id => 'member']]);
    $this->actingAs(admin());

    $this->get(route('invites.poster', $invite))->assertOk()->assertSee('<svg', false)->assertSee('Matematyka');
    $this->get(route('invites.qr', $invite))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

    Livewire::test(Show::class, ['invite' => $invite])->assertSee('<svg', false)->call('revoke');
    expect($invite->fresh()->revoked_at)->not->toBeNull();
});

it('does not expose invites of another school', function () {
    $other = School::factory()->create();
    actAsSchool($other);
    $group = Group::factory()->create();
    [$foreign] = makeInvite(['groups' => [$group->id => 'member']]);
    actAsSchool($this->school);

    $this->actingAs(admin())->get(route('invites.poster', $foreign))->assertForbidden();
});
