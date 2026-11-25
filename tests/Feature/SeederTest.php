<?php

use App\Models\Group;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds roles, starter groups and the first administrator idempotently', function () {
    config(['mci.admin.email' => 'admin@szkola.edu.pl', 'mci.admin.password' => 'TajneHaslo123']);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@szkola.edu.pl')->sole();
    expect($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->isActive())->toBeTrue()
        ->and(Group::query()->where('slug', 'wszyscy-pracownicy')->sole()->is_system)->toBeTrue()
        ->and(Group::query()->where('slug', 'nauczyciele-przedmiotow-zawodowych')->first()->parent->slug)->toBe('nauczyciele');
});

it('renders every main page for the administrator', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = admin();
    $group = Group::query()->first();

    foreach ([
        route('dashboard'), route('groups.index'), route('groups.create'), route('groups.show', $group), route('groups.edit', $group),
        route('admin.users.index'), route('admin.users.create'), route('admin.users.import'), route('admin.users.edit', $admin),
        route('admin.appearance'), route('admin.audit'), route('profile'),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});
