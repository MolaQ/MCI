<?php

use App\Enums\GroupRole;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Livewire\Admin\Users\Edit;
use App\Livewire\Admin\Users\Index;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\User;
use App\Notifications\AccountSetupNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('forbids teachers from the user administration', function () {
    $this->actingAs(userWithRole(Role::Teacher))
        ->get(route('admin.users.index'))->assertForbidden();
});

it('lists and filters users', function () {
    $this->actingAs(admin());
    User::factory()->create(['first_name' => 'Zofia', 'last_name' => 'Wiśniewska']);
    User::factory()->create(['first_name' => 'Piotr', 'last_name' => 'Zieliński']);

    Livewire::test(Index::class)
        ->set('search', 'Wiśniew')
        ->assertSee('Wiśniewska')
        ->assertDontSee('Zieliński');
});

it('creates an account with a role, several groups and a set-up e-mail', function () {
    Notification::fake();
    $this->actingAs(admin());
    $math = Group::factory()->create(['name' => 'Matematyka']);
    $tutors = Group::factory()->create(['name' => 'Wychowawcy']);

    Livewire::test(Edit::class)
        ->set('first_name', 'Anna')
        ->set('last_name', 'Kowalska')
        ->set('email', ' Anna.Kowalska@Szkola.edu.pl ')
        ->set('roles', ['teacher'])
        ->set("memberships.{$math->id}", 'leader')
        ->set("memberships.{$tutors->id}", 'member')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'anna.kowalska@szkola.edu.pl')->firstOrFail();

    expect($user->status)->toBe(UserStatus::Invited)
        ->and($user->hasRole('teacher'))->toBeTrue()
        ->and($user->leadsGroup($math))->toBeTrue()
        ->and($user->groups()->pluck('groups.id')->sort()->values()->all())->toBe(collect([$math->id, $tutors->id])->sort()->values()->all());

    Notification::assertSentTo($user, AccountSetupNotification::class);
    expect(AuditLog::query()->where('action', 'user.created')->exists())->toBeTrue();
});

it('validates unique e-mail addresses', function () {
    $this->actingAs(admin());
    User::factory()->create(['email' => 'zajety@szkola.edu.pl']);

    Livewire::test(Edit::class)
        ->set('first_name', 'A')->set('last_name', 'B')
        ->set('email', 'zajety@szkola.edu.pl')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

it('prevents a user manager without roles.manage from changing access', function () {
    $manager = userWithRole(Role::Teacher);
    $manager->givePermissionTo('users.manage');
    $this->actingAs($manager);

    $target = userWithRole(Role::Teacher);

    Livewire::test(Edit::class, ['user' => $target])
        ->set('roles', ['admin'])
        ->set('permissions', ['alerts.send'])
        ->call('save')
        ->assertHasNoErrors();

    $target->refresh();
    expect($target->hasRole('admin'))->toBeFalse()
        ->and($target->hasDirectPermission('alerts.send'))->toBeFalse();
});

it('does not let a non-admin edit an administrator', function () {
    $manager = userWithRole(Role::Coordinator);
    $manager->givePermissionTo('users.manage');

    $this->actingAs($manager)->get(route('admin.users.edit', admin()))->assertForbidden();
});

it('records role and permission changes in the audit log', function () {
    $this->actingAs(admin());
    $target = userWithRole(Role::Teacher);

    Livewire::test(Edit::class, ['user' => $target])
        ->set('roles', ['coordinator'])
        ->set('permissions', ['alerts.send'])
        ->call('save');

    expect(AuditLog::query()->where('action', 'user.roles_changed')->where('subject_id', $target->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.permissions_changed')->where('subject_id', $target->id)->exists())->toBeTrue()
        ->and($target->fresh()->can('alerts.send'))->toBeTrue();
});

it('suspends and restores an account but never your own', function () {
    $admin = admin();
    $this->actingAs($admin);
    $target = User::factory()->create();

    Livewire::test(Index::class)->call('toggleSuspension', $target->id);
    expect($target->fresh()->status)->toBe(UserStatus::Suspended);

    Livewire::test(Index::class)->call('toggleSuspension', $target->id);
    expect($target->fresh()->status)->toBe(UserStatus::Active);

    Livewire::test(Index::class)->call('toggleSuspension', $admin->id)->assertForbidden();
});

it('soft-deletes an account and removes its memberships', function () {
    $this->actingAs(admin());
    $group = Group::factory()->create();
    $target = User::factory()->create();
    $group->members()->attach($target->id, ['role' => GroupRole::Member->value]);

    Livewire::test(Edit::class, ['user' => $target])->call('delete');

    expect(User::query()->find($target->id))->toBeNull()
        ->and(User::withTrashed()->find($target->id))->not->toBeNull()
        ->and($group->members()->count())->toBe(0);
});
