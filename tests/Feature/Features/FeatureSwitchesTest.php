<?php

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Domain\Invites\Actions\CreateInvite;
use App\Enums\Feature;
use App\Enums\MembershipMode;
use App\Enums\Role;
use App\Livewire\Admin\SchoolFeatures;
use App\Livewire\Events\Edit as EventEdit;
use App\Livewire\Site\Features as SiteFeatures;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Group;
use App\Models\School;
use App\Support\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function features(): Features
{
    return app(Features::class);
}

function publicEvent(School $school, string $title): Event
{
    return Event::query()->create([
        'school_id' => $school->id, 'title' => $title, 'category_id' => categoryId('open_day'),
        'starts_at' => now()->addDays(3), 'visibility' => 'public',
    ]);
}

it('enables every feature by default and names each one in Polish', function () {
    foreach (Feature::cases() as $feature) {
        expect(features()->enabled($feature))->toBeTrue()
            ->and($feature->label())->not->toStartWith('features.')
            ->and($feature->description())->not->toStartWith('features.');
    }
});

it('lets the site admin switch a module off everywhere, and schools cannot turn it back on', function () {
    $this->actingAs(siteAdmin());
    Livewire::test(SiteFeatures::class)->call('toggle', 'events', 'on');

    expect(features()->enabled(Feature::Events))->toBeFalse()
        ->and(features()->enabled(Feature::PublicEvents))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'features.site_updated')->exists())->toBeTrue();

    $this->get(route('events.index'))->assertNotFound();
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('events.index'));

    Livewire::test(SchoolFeatures::class)->call('toggle', 'events');
    expect(features()->enabled(Feature::Events))->toBeFalse();
});

it('lets a school switch a feature off for itself only, with an audit entry', function () {
    $other = School::factory()->create();
    $admin = admin();
    $this->actingAs($admin);

    Livewire::test(SchoolFeatures::class)->call('toggle', 'events');

    expect(features()->enabled(Feature::Events, $this->school->fresh()))->toBeFalse()
        ->and(features()->enabled(Feature::Events, $other))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'school.feature_updated')->where('school_id', $this->school->id)->exists())->toBeTrue();
    $this->get(route('events.index'))->assertNotFound();

    Livewire::test(SchoolFeatures::class)->call('toggle', 'events');
    expect(features()->enabled(Feature::Events, $this->school->fresh()))->toBeTrue();
});

it('ignores school values for features the site locked', function () {
    features()->setForSchool($this->school, Feature::Mentions, false);
    expect(features()->enabled(Feature::Mentions, $this->school))->toBeFalse();

    features()->setSite(Feature::Mentions, true, false);
    expect(features()->enabled(Feature::Mentions, $this->school))->toBeTrue();

    $this->actingAs(admin());
    Livewire::test(SchoolFeatures::class)->call('toggle', 'announcements.mentions');
    expect(features()->schoolValue($this->school->fresh(), Feature::Mentions))->toBeFalse()
        ->and(features()->enabled(Feature::Mentions, $this->school->fresh()))->toBeTrue();
});

it('hides a school from the public front when it switches the portal off', function () {
    $other = School::factory()->create(['short_name' => 'ZS2', 'slug' => 'zs2']);
    $hidden = publicEvent($this->school, 'Ukryty dzień otwarty');
    publicEvent($other, 'Widoczny turniej');
    features()->setForSchool($this->school, Feature::PublicFront, false);

    $this->get(route('home'))->assertSee('Widoczny turniej')->assertDontSee('Ukryty dzień otwarty');
    $this->get(route('public.school', $this->school->slug))->assertNotFound();
    $this->get(route('public.event', $hidden))->assertNotFound();
});

it('stops iCal and public events per school', function () {
    $event = publicEvent($this->school, 'Konkurs');
    features()->setForSchool($this->school, Feature::EventIcs, false);
    $this->get(route('public.event.ics', $event))->assertNotFound();
    $this->get(route('public.event', $event))->assertOk()->assertDontSee(route('public.event.ics', $event));

    features()->setForSchool($this->school, Feature::PublicEvents, false);
    $this->get(route('public.event', $event))->assertNotFound();

    $this->actingAs(userWithRole(Role::Coordinator));
    Livewire::test(EventEdit::class)
        ->set('title', 'X')->set('date', now()->addDay()->format('Y-m-d'))->set('start_time', '10:00')
        ->set('visibility', 'public')->call('save')->assertHasErrors('visibility');
});

it('sends to the site home or login when the whole portal is off', function () {
    features()->setSite(Feature::PublicFront, false, true);

    $this->get(route('home'))->assertRedirect(route('login'));
    $this->actingAs(admin())->get(route('home'))->assertRedirect(route('dashboard'));
});

it('applies communication switches in the publishing action', function () {
    $author = userWithRole(Role::Coordinator);
    $jan = userWithRole(Role::Teacher, ['first_name' => 'Jan', 'last_name' => 'Nowak']);
    actAsSchool($this->school, $author);
    $publish = fn (array $data, array $images = []) => app(PublishAnnouncement::class)
        ->handle($author, $this->school, $data + ['title' => 'T', 'body' => '<p>x</p>'], ['role:teacher'], $images);

    features()->setForSchool($this->school, Feature::Mentions, false);
    $a = $publish(['body' => '<p><span data-type="mention" data-id="user:'.$jan->id.'" data-label="Jan Nowak">@Jan Nowak</span></p>']);
    expect($a->body)->not->toContain('data-type')->and($a->mentions()->count())->toBe(0)
        ->and($jan->notifications()->count())->toBe(0);
    $this->actingAs($jan)->getJson(route('mentions.search', ['q' => 'Jan']))->assertExactJson([]);

    features()->setForSchool($this->school, Feature::AnnouncementConfirmation, false);
    expect($publish(['requires_confirmation' => true])->requires_confirmation)->toBeFalse();

    features()->setForSchool($this->school, Feature::AnnouncementScheduling, false);
    expect(fn () => $publish(['publish_at' => now()->addDay()]))->toThrow(ValidationException::class);

    features()->setForSchool($this->school, Feature::AnnouncementImages, false);
    expect(fn () => $publish([], [UploadedFile::fake()->image('a.jpg')]))->toThrow(ValidationException::class);

    features()->setForSchool($this->school, Feature::Announcements, false);
    $this->actingAs($author)->get(route('communication.index'))->assertNotFound();
});

it('turns off self-joining open groups and invite links', function () {
    $group = Group::factory()->create(['membership_mode' => MembershipMode::Open]);
    $teacher = userWithRole(Role::Teacher);
    expect($teacher->can('join', $group))->toBeTrue();

    features()->setForSchool($this->school, Feature::GroupSelfJoin, false);
    expect($teacher->fresh()->can('join', $group))->toBeFalse();

    [, $token] = app(CreateInvite::class)->handle(admin(), $this->school, ['kind' => 'group_link', 'groups' => [$group->id => 'member']]);
    $this->get(route('join.show', $token))->assertOk();
    features()->setForSchool($this->school, Feature::Invites, false);
    $this->get(route('join.show', $token))->assertNotFound();
});

it('protects both feature screens', function () {
    $this->actingAs(admin())->get(route('site.features'))->assertForbidden();
    $this->actingAs(userWithRole(Role::Coordinator))->get(route('admin.school.features'))->assertForbidden();
    $this->actingAs(admin())->get(route('admin.school.features'))->assertOk()->assertSee(Feature::Mentions->label());
    $this->actingAs(siteAdmin())->get(route('site.features'))->assertOk()->assertSee(Feature::BackgroundRemoval->label());
});
