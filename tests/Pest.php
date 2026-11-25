<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutVite();
    })
    ->in('Feature');

function userWithRole(Role|string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role instanceof Role ? $role->value : $role);

    return $user;
}

function admin(): User
{
    return userWithRole(Role::Admin);
}
