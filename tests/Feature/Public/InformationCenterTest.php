<?php

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Enums\Feature;
use App\Enums\Role;
use App\Livewire\Publicly\Home;
use App\Models\Event;
use App\Models\School;
use App\Support\Features;
use Livewire\Livewire;

function infoEvent(array $attributes = []): Event
{
    return Event::query()->create($attributes + [
        'school_id' => test()->school->id,
        'title' => 'Wydarzenie',
        'category_id' => categoryId('meeting'),
        'starts_at' => now()->addDays(3)->setTime(10, 0),
        'visibility' => 'public',
        'description' => 'Notatka wewnętrzna',
        'public_description' => 'Opis publiczny',
    ]);
}

it('shows a live summary, quick actions and a featured bento', function () {
    infoEvent(['title' => 'Dzień Otwarty ZSM', 'category_id' => categoryId('open_day'), 'starts_at' => now()->addDays(10)]);
    infoEvent(['title' => 'Zebranie z rodzicami', 'starts_at' => now()->addDay()]);
    infoEvent(['title' => 'Rada pedagogiczna', 'visibility' => 'school_only']);

    $author = userWithRole(Role::Coordinator);
    actAsSchool($this->school, $author);
    app(PublishAnnouncement::class)->handle($author, $this->school, ['title' => 'Rekrutacja ruszyła', 'is_public' => true, 'body' => '<p>Zapraszamy</p>'], ['everyone']);

    $html = $this->get(route('home'))->assertOk()
        ->assertSee('Centrum informacji')
        ->assertSee('Zaloguj się')->assertSee('Dołącz z zaproszenia')->assertSee('Tryb TV')
        ->assertSee('Wyróżnione')->assertSee('Rekrutacja ruszyła')
        ->assertDontSee('Rada pedagogiczna')->assertDontSee('Notatka wewnętrzna')
        ->getContent();

    // The highlighted event leads the bento and is not repeated in the list below.
    expect(substr_count($html, 'Dzień Otwarty ZSM'))->toBe(1)
        ->and(strpos($html, 'Dzień Otwarty ZSM'))->toBeLessThan(strpos($html, 'Zebranie z rodzicami'));

    Livewire::test(Home::class)
        ->assertSet('when', 'upcoming')
        ->assertViewHas('filtered', false)
        ->tap(fn ($c) => expect($c->instance()->stats)->toMatchArray(['schools' => 1, 'announcements' => 1]));
});

it('offers only categories that have something coming up, and filters by them', function () {
    $other = School::factory()->create(['short_name' => 'ZS2', 'slug' => 'zs2']);
    infoEvent(['title' => 'Turniej siatkówki', 'school_id' => $other->id, 'category_id' => categoryId('sport')]);
    infoEvent(['title' => 'Konkurs CAD', 'category_id' => categoryId('competition')]);

    $home = Livewire::test(Home::class);
    expect($home->instance()->categories->pluck('key')->sort()->values()->all())->toBe(['competition', 'sport']);

    $home->set('category', (string) categoryId('sport'))
        ->assertSee('Turniej siatkówki')->assertDontSee('Konkurs CAD')
        ->assertViewHas('filtered', true)
        ->call('clearFilters')
        ->assertSet('category', '')->assertSee('Konkurs CAD');

    Livewire::test(Home::class)->call('setFilter', 'school', 'zs2')
        ->assertSee('Turniej siatkówki')->assertDontSee('Konkurs CAD')
        ->tap(fn ($c) => expect($c->instance()->categories->pluck('key')->all())->toBe(['sport']));
});

it('shows the nearest events first and more on demand', function () {
    foreach (range(1, 8) as $day) {
        infoEvent(['title' => "Spotkanie nr {$day}", 'starts_at' => now()->addDays($day)->setTime(9, 0)]);
    }
    infoEvent(['title' => 'Zeszłoroczne', 'starts_at' => now()->subYear()]);

    Livewire::test(Home::class)
        ->assertSee('Spotkanie nr 1')->assertSee('Spotkanie nr 6')->assertDontSee('Spotkanie nr 7')->assertDontSee('Zeszłoroczne')
        ->assertSee('Pokaż więcej')
        ->call('showMore')
        ->assertSee('Spotkanie nr 8')->assertDontSee('Pokaż więcej');

    Livewire::test(Home::class)->call('setFilter', 'when', 'today')->assertDontSee('Spotkanie nr 1');
    Livewire::test(Home::class)->call('setFilter', 'when', 'nonsense')->assertSet('when', 'upcoming');
});

it('previews a listed school in a drawer with public data only', function () {
    $this->school->update(['tagline' => 'Mechatronika i robotyka', 'phone' => '12 345 67 89']);
    $this->school->offers()->create(['kind' => 'technikum', 'name' => 'Technik mechatronik', 'is_active' => true]);
    $this->school->offers()->create(['kind' => 'technikum', 'name' => 'Kierunek wygaszony', 'is_active' => false]);
    infoEvent(['title' => 'Pokaz robotów']);
    infoEvent(['title' => 'Narada wewnętrzna', 'visibility' => 'school_only']);
    $hidden = School::factory()->create(['is_listed' => false, 'slug' => 'ukryta']);

    Livewire::test(Home::class)
        ->call('preview', 'zsm')
        ->assertSet('previewSlug', 'zsm')
        ->assertDispatched('modal-show', name: 'school-preview')
        ->assertSee('Mechatronika i robotyka')->assertSee('12 345 67 89')->assertSee('Technik mechatronik')
        ->assertSee('Pokaz robotów')
        ->assertDontSee('Kierunek wygaszony')->assertDontSee('Narada wewnętrzna')
        ->assertSee(route('public.school.ics', 'zsm'));

    Livewire::test(Home::class)->call('preview', $hidden->slug)->assertSet('previewSlug', null)->assertNotDispatched('modal-show');
});

it('exports a school calendar with its upcoming public events only', function () {
    infoEvent(['title' => 'Dzień Otwarty', 'category_id' => categoryId('open_day')]);
    infoEvent(['title' => 'Rada pedagogiczna', 'visibility' => 'school_only']);
    infoEvent(['title' => 'Stare wydarzenie', 'starts_at' => now()->subMonth()]);
    School::factory()->create(['is_listed' => false, 'slug' => 'ukryta']);

    $ics = $this->get(route('public.school.ics', 'zsm'))->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();

    expect($ics)->toContain('X-WR-CALNAME:Zespół Szkół Mechanicznych')->toContain('SUMMARY:Dzień Otwarty')
        ->not->toContain('Rada pedagogiczna')->not->toContain('Stare wydarzenie')->not->toContain('Notatka');

    $this->get(route('public.school.ics', 'ukryta'))->assertNotFound();

    app(Features::class)->setForSchool($this->school, Feature::EventIcs, false);
    $this->get(route('public.school.ics', 'zsm'))->assertNotFound();
});

it('keeps internal category data off the public front', function () {
    $event = infoEvent(['title' => 'Dzień Otwarty', 'category_id' => categoryId('open_day')]);

    $loaded = Event::query()->publiclyVisible()->findOrFail($event->id);
    expect(array_keys($loaded->category->getAttributes()))->not->toContain('created_by')->not->toContain('color');
});
