<?php

use App\Enums\GroupRole;
use App\Enums\Role;
use App\Livewire\Groups\Edit;
use App\Livewire\Groups\Index;
use App\Livewire\Groups\Show;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;

function groupWithLeader(): array
{
    $group = Group::factory()->create(['name' => 'Wolontariat']);
    $leader = userWithRole(Role::Teacher);
    $group->members()->attach($leader->id, ['role' => GroupRole::Leader->value]);

    return [$group, $leader];
}

it('lets a user belong to many groups at once', function () {
    $user = User::factory()->create();
    $groups = Group::factory(3)->create();
    $user->groups()->attach($groups->pluck('id'), ['role' => 'member']);

    expect($user->groups)->toHaveCount(3);
});

it('lets coordinators create groups but not teachers', function () {
    $this->actingAs(userWithRole(Role::Teacher))->get(route('groups.create'))->assertForbidden();

    $this->actingAs(userWithRole(Role::Coordinator));
    Livewire::test(Edit::class)
        ->set('name', 'Koło robotyki')
        ->set('type', 'custom')
        ->set('color', '#ff0000')
        ->call('save')
        ->assertHasNoErrors();

    expect(Group::query()->where('slug', 'kolo-robotyki')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'group.created')->exists())->toBeTrue();
});

it('rejects colours that are not hex values', function () {
    $this->actingAs(admin());

    Livewire::test(Edit::class)
        ->set('name', 'X')->set('color', 'red;background:url(x)')
        ->call('save')
        ->assertHasErrors(['color']);
});

it('lets a group leader add and remove members of their own group', function () {
    [$group, $leader] = groupWithLeader();
    $teacher = userWithRole(Role::Teacher);
    $this->actingAs($leader);

    Livewire::test(Show::class, ['group' => $group])->call('addMember', $teacher->id);
    expect($group->members()->whereKey($teacher->id)->exists())->toBeTrue();

    Livewire::test(Show::class, ['group' => $group])->call('removeMember', $teacher->id);
    expect($group->members()->whereKey($teacher->id)->exists())->toBeFalse();
});

it('does not let a leader manage other groups, promote leaders or remove a leader', function () {
    [$group, $leader] = groupWithLeader();
    $other = Group::factory()->create();
    $other->members()->attach($leader->id, ['role' => 'member']);
    $coLeader = userWithRole(Role::Teacher);
    $group->members()->attach($coLeader->id, ['role' => GroupRole::Leader->value]);
    $member = userWithRole(Role::Teacher);
    $group->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($leader);

    Livewire::test(Show::class, ['group' => $other])->call('addMember', $member->id)->assertForbidden();
    Livewire::test(Show::class, ['group' => $group])->call('setRole', $member->id, 'leader')->assertForbidden();
    Livewire::test(Show::class, ['group' => $group])->call('removeMember', $coLeader->id)->assertForbidden();
});

it('lets a leader edit the description but not the structure of their group', function () {
    [$group, $leader] = groupWithLeader();
    $this->actingAs($leader);

    Livewire::test(Edit::class, ['group' => $group])
        ->set('description', 'Nowy opis')
        ->set('membership_mode', 'open')
        ->call('save')
        ->assertHasNoErrors();

    $group->refresh();
    expect($group->description)->toBe('Nowy opis')
        ->and($group->membership_mode->value)->toBe('managed');
});

it('lets coordinators promote a member to leader', function () {
    [$group] = groupWithLeader();
    $member = userWithRole(Role::Teacher);
    $group->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs(userWithRole(Role::Coordinator));
    Livewire::test(Show::class, ['group' => $group])->call('setRole', $member->id, 'leader');

    expect($member->fresh()->leadsGroup($group))->toBeTrue();
});

it('lets anyone join and leave an open group, but not a managed one', function () {
    $open = Group::factory()->open()->create();
    $managed = Group::factory()->create();
    $teacher = userWithRole(Role::Teacher);
    $this->actingAs($teacher);

    Livewire::test(Show::class, ['group' => $open])->call('join');
    expect($open->members()->whereKey($teacher->id)->exists())->toBeTrue();

    Livewire::test(Show::class, ['group' => $open])->call('leave');
    expect($open->members()->whereKey($teacher->id)->exists())->toBeFalse();

    $this->get(route('groups.show', $managed))->assertForbidden();
});

it('shows teachers only their own and open groups', function () {
    $teacher = userWithRole(Role::Teacher);
    $mine = Group::factory()->create(['name' => 'Moja grupa']);
    $mine->members()->attach($teacher->id, ['role' => 'member']);
    Group::factory()->open()->create(['name' => 'Otwarta grupa']);
    Group::factory()->create(['name' => 'Cudza grupa']);

    $this->actingAs($teacher);
    Livewire::test(Index::class)
        ->assertSee('Moja grupa')
        ->assertSee('Otwarta grupa')
        ->assertDontSee('Cudza grupa');
});

it('protects system groups from deletion', function () {
    $system = Group::factory()->create(['is_system' => true]);
    $this->actingAs(admin());

    Livewire::test(Edit::class, ['group' => $system])->call('delete')->assertForbidden();
});
