<?php

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Enums\ProfileVisibility;
use App\Enums\Role;
use App\Livewire\Admin\SchoolProfile;
use App\Livewire\Events\Edit as EventEdit;
use App\Models\Event;
use App\Models\School;
use App\Models\User;
use Livewire\Livewire;

function makeEvent(array $attributes = []): Event
{
    return Event::query()->create($attributes + [
        'school_id' => test()->school->id,
        'title' => 'Wydarzenie',
        'category' => 'open_day',
        'starts_at' => now()->addDays(3)->setTime(10, 0),
        'visibility' => 'public',
        'description' => 'Notatka wewnętrzna dla kadry',
        'public_description' => 'Opis dla wszystkich',
    ]);
}

it('shows only public events of listed schools to guests, without internal fields', function () {
    $public = makeEvent(['title' => 'Dzień Otwarty']);
    $internal = makeEvent(['title' => 'Rada pedagogiczna', 'visibility' => 'school_only']);
    $inter = makeEvent(['title' => 'Szkolenie wspólne', 'visibility' => 'inter_school']);
    $hiddenSchool = School::factory()->create(['is_listed' => false, 'slug' => 'ukryta']);
    $unlisted = makeEvent(['title' => 'Ukryte', 'school_id' => $hiddenSchool->id]);

    $this->get(route('home'))->assertOk()
        ->assertSee('Dzień Otwarty')->assertSee($this->school->short_name)
        ->assertDontSee('Rada pedagogiczna')->assertDontSee('Szkolenie wspólne')->assertDontSee('Ukryte');

    $this->get(route('public.event', $public))->assertOk()
        ->assertSee('Opis dla wszystkich')->assertDontSee('Notatka wewnętrzna');

    foreach ([$internal, $inter, $unlisted] as $event) {
        $this->get(route('public.event', $event))->assertNotFound();
        $this->get(route('public.event.ics', $event))->assertNotFound();
    }

    $this->get(route('public.school', 'ukryta'))->assertNotFound();
    $this->get(route('public.school', $this->school->slug))->assertOk();
    $this->get(route('tv'))->assertOk()->assertSee('Dzień Otwarty')->assertDontSee('Rada pedagogiczna');
});

it('filters the public front with pills in the URL', function () {
    $other = School::factory()->create(['short_name' => 'ZS2', 'slug' => 'zs2']);
    makeEvent(['title' => 'Turniej siatkówki', 'school_id' => $other->id, 'category' => 'sport']);
    makeEvent(['title' => 'Konkurs CAD', 'category' => 'competition']);

    $this->get(route('home', ['szkola' => 'zs2']))->assertSee('Turniej siatkówki')->assertDontSee('Konkurs CAD');
    $this->get(route('home', ['kategoria' => 'competition']))->assertSee('Konkurs CAD')->assertDontSee('Turniej siatkówki');
});

it('exports a public event as iCal with an exclusive all-day end', function () {
    $event = makeEvent(['title' => 'Zbiórka, karma; koce', 'all_day' => true, 'starts_at' => '2026-12-01 00:00:00']);

    $ics = $this->get(route('public.event.ics', $event))->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();

    expect($ics)->toContain('DTSTART;VALUE=DATE:20261201')->toContain('DTEND;VALUE=DATE:20261202')
        ->toContain('SUMMARY:Zbiórka\, karma\; koce')->not->toContain('Notatka');
});

it('publishes announcements publicly only when marked, hiding private people', function () {
    $author = userWithRole(Role::Coordinator);
    $private = userWithRole(Role::Teacher, ['first_name' => 'Anna', 'last_name' => 'Prywatna']);
    actAsSchool($this->school, $author);
    $publish = app(PublishAnnouncement::class);

    $open = $publish->handle($author, $this->school, ['title' => 'Zapraszamy', 'is_public' => true,
        'body' => '<p>Prowadzi <span data-type="mention" data-id="user:'.$private->id.'" data-label="x">@x</span></p>'], ['everyone']);
    $internal = $publish->handle($author, $this->school, ['title' => 'Wewnętrzny', 'body' => '<p>Tylko kadra</p>'], ['everyone']);

    expect($private->effectiveProfileVisibility())->not->toBe(ProfileVisibility::Public);
    $this->get(route('public.announcement', $open))->assertOk()->assertSee('Zapraszamy')->assertSee('@…')->assertDontSee('Prywatna');
    $this->get(route('public.announcement', $internal))->assertNotFound();
    $this->get(route('home'))->assertSee('Zapraszamy')->assertDontSee('Wewnętrzny');
});

it('needs the public-publishing permission to make an event public', function () {
    $teacher = userWithRole(Role::Teacher);
    $this->actingAs($teacher);

    Livewire::test(EventEdit::class)
        ->set('title', 'Wycieczka')->set('category', 'other')
        ->set('date', now()->addDay()->format('Y-m-d'))->set('start_time', '10:00')
        ->set('visibility', 'public')
        ->call('save')->assertHasErrors('visibility');

    expect(Event::query()->where('title', 'Wycieczka')->exists())->toBeFalse();

    Livewire::test(EventEdit::class)
        ->set('title', 'Wycieczka')->set('category', 'other')
        ->set('date', now()->addDay()->format('Y-m-d'))->set('start_time', '10:00')
        ->set('visibility', 'school_only')
        ->call('save')->assertHasNoErrors();

    $event = Event::query()->where('title', 'Wycieczka')->sole();
    expect($event->owner_id)->toBe($teacher->id);

    // Another school's teacher cannot see a school-only event; a colleague can.
    $colleague = userWithRole(Role::Teacher);
    $stranger = userWithRole(Role::Teacher, school: School::factory()->create());
    $this->actingAs($colleague)->get(route('events.show', $event))->assertOk();
    $this->actingAs($colleague)->get(route('events.edit', $event))->assertForbidden();
    $this->actingAs($stranger)->get(route('events.show', $event))->assertForbidden();
});

it('lets only settings managers edit the public school profile', function () {
    $this->actingAs(userWithRole(Role::Coordinator))->get(route('admin.school.profile'))->assertForbidden();

    $this->actingAs(admin());
    Livewire::test(SchoolProfile::class)
        ->set('tagline', 'Najlepsza szkoła w mieście')
        ->call('save')->assertHasNoErrors()
        ->set('offerName', 'Technik robotyk')->set('offerKind', 'technikum')
        ->call('addOffer')->assertHasNoErrors();

    $this->get(route('public.school', $this->school->slug))->assertSee('Najlepsza szkoła w mieście')->assertSee('Technik robotyk');
});

it('never puts private user data on the public front', function () {
    userWithRole(Role::Teacher, ['first_name' => 'Ukryty', 'email' => 'ukryty@szkola.edu.pl']);

    $this->get(route('home'))->assertDontSee('ukryty@szkola.edu.pl')->assertDontSee('Ukryty');
    $this->get(route('public.school', $this->school->slug))->assertDontSee('ukryty@szkola.edu.pl');
    expect(User::query()->count())->toBeGreaterThan(0);
});
