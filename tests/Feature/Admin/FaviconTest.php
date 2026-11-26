<?php

use App\Livewire\Admin\Appearance;
use App\Models\AuditLog;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('serves a default favicon as ICO and PNG, linked from every page', function () {
    $ico = $this->get(route('favicon'))->assertOk()->assertHeader('Content-Type', 'image/x-icon');
    // ICONDIR: reserved 0, type 1 (icon), 3 images (16, 32, 48).
    expect(substr($ico->getContent(), 0, 6))->toBe(pack('vvv', 0, 1, 3));

    $png = $this->get(route('favicon.png', 32))->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(getimagesizefromstring($png->getContent())[0])->toBe(32);

    $this->get(route('favicon.png', 99))->assertNotFound();
    $this->get('/?podglad=1')->assertSee('favicon.ico?v=', false)->assertSee('favicon-180.png', false);
});

it('lets the site admin upload and remove a favicon', function () {
    Storage::fake('media');
    $this->actingAs(siteAdmin());

    Livewire::test(Appearance::class)
        ->set('favicon', UploadedFile::fake()->image('ikona.png', 256, 256))
        ->call('save')
        ->assertHasNoErrors();

    $id = app(Settings::class)->get('appearance')['favicon_media_id'] ?? null;
    expect($id)->not->toBeNull();
    $this->get(route('favicon.png', 48))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('pwa.manifest'))->assertOk();

    Livewire::test(Appearance::class)->call('removeFavicon');
    expect(app(Settings::class)->get('appearance')['favicon_media_id'] ?? null)->toBeNull()
        ->and(AuditLog::query()->where('action', 'settings.favicon_removed')->exists())->toBeTrue();
});
