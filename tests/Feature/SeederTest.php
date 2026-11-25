<?php

use App\Models\Group;
use App\Models\School;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds roles, starter groups and the first administrator idempotently', function () {
    config(['mci.admin.email' => 'admin@szkola.edu.pl', 'mci.admin.password' => 'TajneHaslo123']);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    // The test school already existed, so SchoolSeeder created no extra one.
    expect(School::query()->count())->toBe(1);

    $admin = User::query()->where('email', 'admin@szkola.edu.pl')->sole();
    actAsSchool($this->school, $admin);
    expect($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->is_site_admin)->toBeTrue()
        ->and($admin->isActive())->toBeTrue()
        ->and($admin->belongsToSchool($this->school))->toBeTrue()
        ->and(Group::query()->where('slug', 'wszyscy-pracownicy')->sole()->is_system)->toBeTrue()
        ->and(Group::query()->where('slug', 'nauczyciele-przedmiotow-zawodowych')->first()->parent->slug)->toBe('nauczyciele');
});

it('renders every main page for the administrator', function () {
    $this->seed(DatabaseSeeder::class);
    actAsSchool($this->school);
    $admin = siteAdmin();
    $group = Group::query()->first();

    foreach ([
        route('dashboard'), route('groups.index'), route('groups.create'), route('groups.show', $group), route('groups.edit', $group),
        route('admin.users.index'), route('admin.users.create'), route('admin.users.import'), route('admin.users.edit', $admin),
        route('site.appearance'), route('admin.audit'), route('profile'), route('admin.school'),
        route('site.schools'), route('site.schools.create'), route('site.schools.edit', $this->school), route('site.journal'),
        route('invites.index'), route('invites.create'),
        route('communication.index'), route('communication.create'), route('events.index'), route('events.create'),
        route('admin.school.profile'), route('home'), route('tv'), route('admin.school.features'), route('site.features'), route('settings.notifications'), route('admin.school.catalog'), route('site.catalog'),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});
