<?php

use App\Enums\GroupRole;
use App\Enums\Role;
use App\Enums\SchoolMemberStatus;
use App\Enums\UserStatus;
use App\Livewire\Admin\Users\Edit;
use App\Livewire\Admin\Users\Index;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\School;
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
    member(['first_name' => 'Zofia', 'last_name' => 'Wiśniewska']);
    member(['first_name' => 'Piotr', 'last_name' => 'Zieliński']);
    // Someone from another school never appears in this school's list.
    member(['first_name' => 'Obca', 'last_name' => 'Wiśniewska-Obca'], School::factory()->create());

    Livewire::test(Index::class)
        ->set('search', 'Wiśniew')
        ->assertSee('Wiśniewska')
        ->assertDontSee('Zieliński')
        ->assertDontSee('Wiśniewska-Obca');
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

it('suspends and restores access to the school (not the whole account), but never your own', function () {
    $admin = admin();
    $this->actingAs($admin);
    $target = member();

    Livewire::test(Index::class)->call('toggleSuspension', $target->id);
    expect($target->schoolStatus($this->school))->toBe(SchoolMemberStatus::Suspended)
        ->and($target->fresh()->status)->toBe(UserStatus::Active);

    Livewire::test(Index::class)->call('toggleSuspension', $target->id);
    expect($target->schoolStatus($this->school))->toBe(SchoolMemberStatus::Active);

    Livewire::test(Index::class)->call('toggleSuspension', $admin->id)->assertForbidden();
});

it('removes a person from the school without touching their other schools', function () {
    $this->actingAs(admin());
    $group = Group::factory()->create();
    $target = userWithRole(Role::Teacher);
    $group->members()->attach($target->id, ['role' => GroupRole::Member->value]);

    $other = School::factory()->create();
    $otherGroup = Group::factory()->create(['school_id' => $other->id]);
    $target->schools()->attach($other->id, ['status' => 'active']);
    $otherGroup->members()->attach($target->id, ['role' => 'member']);

    Livewire::test(Edit::class, ['user' => $target])->call('removeFromSchool');

    $target->refresh();
    expect($target->belongsToSchool($this->school))->toBeFalse()
        ->and($target->belongsToSchool($other))->toBeTrue()
        ->and($group->members()->count())->toBe(0)
        ->and($otherGroup->members()->count())->toBe(1)
        ->and($target->unsetRelation('roles')->hasRole('teacher'))->toBeFalse();
});

it('lets only site admins delete a whole account', function () {
    $target = userWithRole(Role::Teacher);

    $this->actingAs(admin());
    Livewire::test(Edit::class, ['user' => $target])->call('deleteAccount')->assertForbidden();

    $this->actingAs(siteAdmin());
    Livewire::test(Edit::class, ['user' => $target])->call('deleteAccount');
    expect(User::query()->find($target->id))->toBeNull()
        ->and(User::withTrashed()->find($target->id))->not->toBeNull();
});
