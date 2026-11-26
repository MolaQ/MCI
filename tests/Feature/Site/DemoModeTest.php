<?php

use App\Enums\Role;
use App\Livewire\Site\DemoMode as DemoPage;
use App\Models\AuditLog;
use App\Support\DemoMode;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

afterEach(fn () => File::delete(config('mci.demo.flag')));

it('never points demo operations at the main database', function () {
    config(['mci.demo.database' => DemoMode::mainDatabaseName()]);
    expect(fn () => DemoMode::assertSafe())->toThrow(RuntimeException::class);

    config(['mci.demo.database' => 'mci_kopia']);
    expect(fn () => DemoMode::assertSafe())->toThrow(RuntimeException::class);

    config(['mci.demo.database' => 'mci_demo']);
    DemoMode::assertSafe();
    expect(true)->toBeTrue();
});

it('refuses to seed demo data outside the demo connection', function () {
    expect(fn () => $this->seed(DemoDatabaseSeeder::class))->toThrow(RuntimeException::class);
});

it('switches off with a flag file and logs it in the main journal', function () {
    File::ensureDirectoryExists(dirname(config('mci.demo.flag')));
    File::put(config('mci.demo.flag'), '{}');
    expect(DemoMode::isOn())->toBeTrue();

    DemoMode::disable(siteAdmin());

    expect(DemoMode::isOn())->toBeFalse()
        ->and(AuditLog::query()->whereNull('school_id')->where('action', 'demo.disabled')->exists())->toBeTrue();
});

it('is for site admins only and needs the password and DEMO typed', function () {
    $this->actingAs(admin())->get(route('site.demo'))->assertForbidden();
    $this->actingAs(userWithRole(Role::Coordinator))->get(route('site.demo'))->assertForbidden();

    $site = siteAdmin();
    $this->actingAs($site)->get(route('site.demo'))->assertOk()->assertSee('Tryb demo');

    Livewire::test(DemoPage::class)->set('password', 'zle')->set('confirmation', 'DEMO')->call('enable')->assertHasErrors('password');
    Livewire::test(DemoPage::class)->set('password', 'password')->set('confirmation', 'tak')->call('enable')->assertHasErrors('confirmation');
    expect(DemoMode::isOn())->toBeFalse();
});

it('shows grouped navigation with the projects module', function () {
    $this->actingAs(admin())->get(route('dashboard'))->assertOk()
        ->assertSee('data-nav-section="work"', false)
        ->assertSee(route('projects.index'), false);
});
