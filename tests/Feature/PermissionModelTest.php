<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role as RoleModel;

it('seeds every role and permission', function () {
    foreach (Role::cases() as $role) {
        expect(RoleModel::findByName($role->value))->not->toBeNull();
    }

    expect(Spatie\Permission\Models\Permission::count())->toBe(count(Permission::cases()));
});

it('does not give alerts.send to any role except admin by default', function () {
    expect(userWithRole(Role::Coordinator)->can('alerts.send'))->toBeFalse()
        ->and(userWithRole(Role::Teacher)->can('alerts.send'))->toBeFalse()
        ->and(admin()->can('alerts.send'))->toBeTrue();
});

it('allows alerts.send to be granted to one person directly', function () {
    $teacher = userWithRole(Role::Teacher);
    $teacher->givePermissionTo(Permission::AlertsSend->value);

    expect($teacher->fresh()->can('alerts.send'))->toBeTrue()
        ->and(userWithRole(Role::Teacher)->can('alerts.send'))->toBeFalse();
});

it('gives students no permissions in phase one', function () {
    expect(userWithRole(Role::Student)->getAllPermissions())->toBeEmpty();
});

it('keeps permission changes made by an admin when the seeder runs again', function () {
    RoleModel::findByName('teacher')->givePermissionTo('invites.create');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(RoleModel::findByName('teacher')->hasPermissionTo('invites.create'))->toBeTrue();
});

it('lets a user without a role log in but see no admin area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk();
    $this->actingAs($user)->get('/administracja/uzytkownicy')->assertForbidden();
});
