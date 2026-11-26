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

it('follows the flag even when the configuration was cached in demo mode', function () {
    $main = config('mci.demo.main_connection');
    $prefix = config('cache.prefix');

    // config:cache while demo mode is on: the cached file holds demo values.
    File::ensureDirectoryExists(dirname(config('mci.demo.flag')));
    File::put(config('mci.demo.flag'), '{}');
    DemoMode::boot();
    expect(config('database.default'))->toBe(DemoMode::CONNECTION);
    $cached = config()->all();

    // Flag removed, app boots again from that cached configuration.
    File::delete(config('mci.demo.flag'));
    config()->set($cached);
    DemoMode::boot();
    expect(config('database.default'))->toBe($main)
        ->and(config('cache.prefix'))->toBe($prefix)
        ->and(config('filesystems.disks.media.root'))->toBe(storage_path('app/media'));

    // Switching on again does not stack suffixes.
    File::put(config('mci.demo.flag'), '{}');
    DemoMode::boot();
    DemoMode::boot();
    expect(config('cache.prefix'))->toBe($prefix.'demo_');

    File::delete(config('mci.demo.flag'));
    DemoMode::boot();
    expect(config('database.default'))->toBe($main);
});

it('recovers from a cache written by the old switch without a remembered base', function () {
    $main = config('mci.demo.main_connection');
    $prefix = config('cache.prefix');
    config([
        'mci.demo.base' => null,
        'database.default' => DemoMode::CONNECTION,
        'cache.prefix' => $prefix.'demo_',
        'session.cookie' => config('session.cookie').'_demo',
        'filesystems.disks.media.root' => storage_path('app/demo/media'),
    ]);

    DemoMode::boot();

    expect(config('database.default'))->toBe($main)
        ->and(config('cache.prefix'))->toBe($prefix)
        ->and(config('filesystems.disks.media.root'))->toBe(storage_path('app/media'));
});
