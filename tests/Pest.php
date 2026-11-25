<?php

use App\Enums\Role;
use App\Enums\TermKind;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Support\CurrentSchool;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutVite();

        // Every test runs inside one active school unless it switches.
        $this->school = School::factory()->create(['short_name' => 'ZSM', 'slug' => 'zsm', 'name' => 'Zespół Szkół Mechanicznych']);
        actAsSchool($this->school);
    })
    ->in('Feature');

function actAsSchool(?School $school, ?User $user = null): void
{
    app(CurrentSchool::class)->set($school, $user);
}

/**
 * A user who is a member of the active school with the given role there.
 */
function userWithRole(Role|string $role, array $attributes = [], ?School $school = null): User
{
    $school ??= app(CurrentSchool::class)->get();
    $user = User::factory()->create($attributes);
    $user->schools()->attach($school->id, ['status' => 'active']);
    $user->assignRole($role instanceof Role ? $role->value : $role);

    return $user;
}

function member(array $attributes = [], ?School $school = null): User
{
    $school ??= app(CurrentSchool::class)->get();
    $user = User::factory()->create($attributes);
    $user->schools()->attach($school->id, ['status' => 'active']);

    return $user;
}

/**
 * School administrator of the active school.
 */
function admin(): User
{
    return userWithRole(Role::Admin);
}

/** Id of a portal-wide event category (seeded by the terms migration). */
function categoryId(string $key): int
{
    return Term::idFor(TermKind::EventCategory, $key);
}

/** Id of a portal-wide group type (seeded by the terms migration). */
function groupTypeId(string $key): int
{
    return Term::idFor(TermKind::GroupType, $key);
}

function siteAdmin(): User
{
    $user = admin();
    $user->forceFill(['is_site_admin' => true])->save();

    return $user;
}
