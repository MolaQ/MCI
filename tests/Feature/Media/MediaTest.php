<?php

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Enums\Role;
use App\Livewire\Admin\Appearance;
use App\Livewire\Admin\SchoolProfile;
use App\Livewire\Communication\Compose;
use App\Livewire\Events\Edit as EventEdit;
use App\Livewire\Groups\Edit as GroupEdit;
use App\Models\Event;
use App\Models\Group;
use App\Models\Media;
use App\Models\School;
use App\Support\Appearance as AppearanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('media');
});

function mediaGet(Media $media)
{
    return test()->get($media->url());
}

it('stores the system logo as an optimised public image served without storage:link', function () {
    $this->actingAs(siteAdmin());

    Livewire::test(Appearance::class)
        ->set('logo', UploadedFile::fake()->image('logo.png', 3000, 1000))
        ->call('save')->assertHasNoErrors();

    $media = Media::query()->sole();
    expect($media->is_public)->toBeTrue()
        ->and($media->mime)->toBe('image/webp')
        ->and(max($media->width, $media->height))->toBe(1024)
        ->and(app(AppearanceService::class)->logoUrl())->toBe($media->url())
        ->and($media->url())->toStartWith('/media/');
    Storage::disk('media')->assertExists($media->path);

    auth()->logout();
    mediaGet($media)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('replaces the old logo file only after the new one is saved, and removes it on request', function () {
    $this->actingAs(siteAdmin());
    $component = Livewire::test(Appearance::class)->set('logo', UploadedFile::fake()->image('a.png', 200, 200))->call('save');
    $first = Media::query()->sole();

    Livewire::test(Appearance::class)->set('logo', UploadedFile::fake()->image('b.png', 200, 200))->call('save');
    $second = Media::query()->sole();

    expect($second->id)->not->toBe($first->id);
    Storage::disk('media')->assertMissing($first->path);

    Livewire::test(Appearance::class)->call('removeLogo');
    expect(Media::query()->count())->toBe(0)->and(app(AppearanceService::class)->logoUrl())->toBeNull();
    Storage::disk('media')->assertMissing($second->path);
});

it('rejects files that are not real raster images', function (UploadedFile $file) {
    $this->actingAs(siteAdmin());

    Livewire::test(Appearance::class)->set('logo', $file)->call('save')->assertHasErrors('logo');

    expect(Media::query()->count())->toBe(0);
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'),
    'php disguised as png' => fn () => UploadedFile::fake()->createWithContent('logo.png', "<?php echo 'x'; ?>"),
    'html' => fn () => UploadedFile::fake()->createWithContent('logo.jpg', '<html><script>alert(1)</script></html>'),
]);

it('keeps transparency of uploaded logos', function () {
    $this->actingAs(siteAdmin());
    $img = imagecreatetruecolor(100, 100);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagefilledrectangle($img, 40, 40, 60, 60, imagecolorallocate($img, 255, 0, 0));
    $path = tempnam(sys_get_temp_dir(), 'mci').'.png';
    imagepng($img, $path);

    Livewire::test(Appearance::class)->set('logo', UploadedFile::fake()->createWithContent('logo.png', file_get_contents($path)))->call('save')->assertHasNoErrors();

    $stored = imagecreatefromstring(Storage::disk('media')->get(Media::query()->sole()->path));
    expect((imagecolorat($stored, 2, 2) >> 24) & 127)->toBe(127)
        ->and((imagecolorat($stored, 50, 50) >> 24) & 127)->toBe(0);
});

it('lets school managers set the school logo shown on the public front', function () {
    $this->actingAs(admin());

    Livewire::test(SchoolProfile::class)
        ->set('logoUpload', UploadedFile::fake()->image('szkola.jpg', 400, 400))
        ->call('save')->assertHasNoErrors();

    $school = $this->school->fresh();
    expect($school->logo_media_id)->not->toBeNull()->and($school->logoMedia->is_public)->toBeTrue();

    auth()->logout();
    $this->get(route('home'))->assertSee($school->logoUrl(), false);
    mediaGet($school->logoMedia)->assertOk();

    $this->actingAs(userWithRole(Role::Coordinator))->get(route('admin.school.profile'))->assertForbidden();
});

it('keeps group logos inside the school', function () {
    $group = Group::factory()->create(['name' => 'Koło robotyki']);
    $leader = userWithRole(Role::Teacher);
    $group->members()->attach($leader->id, ['role' => 'leader']);
    $this->actingAs($leader);

    Livewire::test(GroupEdit::class, ['group' => $group])
        ->set('logoUpload', UploadedFile::fake()->image('kolo.png', 300, 300))
        ->call('save')->assertHasNoErrors();

    $media = $group->fresh()->logoMedia;
    expect($media->is_public)->toBeFalse();

    mediaGet($media)->assertOk();
    $outsider = userWithRole(Role::Teacher, school: School::factory()->create());
    $this->actingAs($outsider);
    mediaGet($media)->assertNotFound();
    auth()->logout();
    mediaGet($media)->assertNotFound();
});

it('publishes an event cover only together with a public event', function () {
    $this->actingAs(userWithRole(Role::Coordinator));

    Livewire::test(EventEdit::class)
        ->set('title', 'Dzień Otwarty')->set('category', 'open_day')
        ->set('date', now()->addWeek()->format('Y-m-d'))->set('start_time', '10:00')
        ->set('visibility', 'school_only')
        ->set('coverUpload', UploadedFile::fake()->image('cover.jpg', 2400, 1350))
        ->call('save')->assertHasNoErrors();

    $event = Event::query()->sole();
    $cover = $event->cover;
    expect($cover->is_public)->toBeFalse()->and($cover->width)->toBe(2000);

    auth()->logout();
    mediaGet($cover)->assertNotFound();

    $this->actingAs(userWithRole(Role::Coordinator));
    Livewire::test(EventEdit::class, ['event' => $event])->set('visibility', 'public')->call('save')->assertHasNoErrors();
    auth()->logout();

    mediaGet($cover->fresh())->assertOk();
    $this->get(route('public.event', $event))->assertSee($cover->url(), false);
});

it('attaches images to announcements, visible to recipients only', function () {
    $author = userWithRole(Role::Coordinator);
    $jan = userWithRole(Role::Teacher);
    $other = userWithRole(Role::Staff);
    actAsSchool($this->school, $author);

    $a = app(PublishAnnouncement::class)->handle($author, $this->school, ['title' => 'Zdjęcia', 'body' => '<p>Z wycieczki</p>'],
        ['user:'.$jan->id], [UploadedFile::fake()->image('1.jpg', 800, 600), UploadedFile::fake()->image('2.png', 600, 800)]);

    $images = $a->images;
    expect($images)->toHaveCount(2)->and($images->pluck('position')->all())->toBe([0, 1]);

    $this->actingAs($jan);
    mediaGet($images[0])->assertOk();
    $this->get(route('communication.show', $a))->assertSee($images[1]->url(), false);

    $this->actingAs($other);
    mediaGet($images[0])->assertNotFound();
});

it('adds images in the composer through the editor upload slot', function () {
    $this->actingAs(userWithRole(Role::Coordinator));
    $jan = userWithRole(Role::Teacher);

    Livewire::test(Compose::class)
        ->set('title', 'Galeria')->set('body', '<p>Treść</p>')
        ->call('toggle', 'user:'.$jan->id)
        ->set('newImage', UploadedFile::fake()->image('a.jpg', 300, 200))
        ->set('newImage', UploadedFile::fake()->image('b.jpg', 300, 200))
        ->assertCount('images', 2)
        ->call('removeImage', 0)
        ->assertCount('images', 1)
        ->call('save')->assertHasNoErrors();

    expect(Media::query()->where('collection', 'image')->count())->toBe(1);
});

it('removes stored files when publishing fails', function () {
    $author = userWithRole(Role::Coordinator);
    actAsSchool($this->school, $author);

    expect(fn () => app(PublishAnnouncement::class)->handle($author, $this->school, ['title' => 'X', 'body' => '<p>x</p>'],
        ['everyone'], [UploadedFile::fake()->image('ok.jpg', 100, 100), UploadedFile::fake()->createWithContent('bad.png', 'nope')]))
        ->toThrow(ValidationException::class);

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('media')->allFiles())->toBe([]);
});
