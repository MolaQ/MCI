<?php

use App\Enums\Feature;
use App\Enums\Role;
use App\Enums\TaxonomyKind;
use App\Livewire\Catalog\Manage;
use App\Livewire\Events\Edit as EventEdit;
use App\Livewire\Groups\Edit as GroupEdit;
use App\Livewire\Publicly\Home;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Group;
use App\Models\School;
use App\Models\TaxonomyTerm;
use App\Support\Features;
use App\Support\Taxonomy;
use Livewire\Livewire;

function term(string $key, TaxonomyKind $kind = TaxonomyKind::EventCategory, ?int $schoolId = null): TaxonomyTerm
{
    return TaxonomyTerm::query()->ofKind($kind)->where('key', $key)->where('school_id', $schoolId)->firstOrFail();
}

it('ships the built-in categories and group types as editable site-wide terms', function () {
    expect(TaxonomyTerm::query()->ofKind(TaxonomyKind::EventCategory)->whereNull('school_id')->pluck('key')->all())
        ->toContain('open_day', 'competition', 'other')
        ->and(term('open_day')->name)->toBe('Dzień otwarty')
        ->and(term('club', TaxonomyKind::GroupType)->name)->toBe('Koło / klub');

    $group = Group::factory()->create(['type' => 'sport']);
    expect($group->fresh()->type->label())->toBe('Sport')->and($group->type->icon())->toBe('trophy');
});

it('lets the site admin add, rename, reorder, archive and delete site-wide categories', function () {
    $this->actingAs(siteAdmin());

    $c = Livewire::test(Manage::class, ['scope' => 'site'])
        ->call('startCreate')->set('name', 'Wycieczka')->set('icon', 'map-pin')->call('save')->assertHasNoErrors();
    $trip = term('wycieczka');
    expect($trip->icon)->toBe('map-pin')->and(AuditLog::query()->where('action', 'catalog.created')->whereNull('school_id')->exists())->toBeTrue();

    $c->call('edit', $trip->id)->set('name', 'Wycieczka szkolna')->call('save')->assertHasNoErrors();
    expect($trip->fresh()->name)->toBe('Wycieczka szkolna')->and($trip->fresh()->key)->toBe('wycieczka');

    $before = $trip->fresh()->position;
    $c->call('move', $trip->id, -1);
    expect($trip->fresh()->position)->toBe($before - 1);

    // Duplicate names and unknown icons are rejected.
    $c->call('startCreate')->set('name', 'dzień otwarty')->set('icon', 'calendar')->call('save')->assertHasErrors('name');
    $c->call('startCreate')->set('name', 'Coś')->set('icon', 'evil-icon')->call('save')->assertHasErrors('icon');

    // In use → cannot delete, can archive; archived terms are not offered for new events.
    Event::query()->create(['school_id' => $this->school->id, 'title' => 'X', 'category' => 'wycieczka', 'starts_at' => now()->addDay()]);
    $c->call('delete', $trip->id)->assertHasErrors('term');
    $c->call('toggleActive', $trip->id);
    expect($trip->fresh()->is_active)->toBeFalse()
        ->and(app(Taxonomy::class)->keys(TaxonomyKind::EventCategory, $this->school))->not->toContain('wycieczka');

    $unused = term('charity');
    $c->call('delete', $unused->id)->assertHasNoErrors();
    expect(TaxonomyTerm::query()->find($unused->id))->toBeNull()
        ->and(AuditLog::query()->where('action', 'catalog.deleted')->exists())->toBeTrue();
});

it('lets a school keep its own categories and hide site-wide ones, within the site switch', function () {
    $other = School::factory()->create();
    $admin = admin();
    $this->actingAs($admin);

    $c = Livewire::test(Manage::class)
        ->call('startCreate')->set('name', 'Praktyki zawodowe')->set('icon', 'wrench-screwdriver')->call('save')->assertHasNoErrors();
    $own = TaxonomyTerm::query()->where('school_id', $this->school->id)->sole();
    $c->call('toggleHidden', term('sport')->id);

    $taxonomy = app(Taxonomy::class);
    $taxonomy->forget();
    expect($taxonomy->keys(TaxonomyKind::EventCategory, $this->school->fresh()))->toContain($own->key)->not->toContain('sport')
        ->and($taxonomy->keys(TaxonomyKind::EventCategory, $other))->not->toContain($own->key)->toContain('sport')
        ->and(AuditLog::query()->where('action', 'catalog.hidden')->where('school_id', $this->school->id)->exists())->toBeTrue();

    // The school cannot touch site-wide terms or another school's terms.
    Livewire::test(Manage::class)->call('move', term('open_day')->id, 1)->assertNotFound();
    $foreign = TaxonomyTerm::query()->create(['kind' => TaxonomyKind::EventCategory, 'school_id' => $other->id, 'key' => 'obcy', 'name' => 'Obcy', 'icon' => 'tag']);
    Livewire::test(Manage::class)->call('delete', $foreign->id)->assertNotFound();
    expect($foreign->fresh())->not->toBeNull();

    // Site switch off → no own list, hidden terms come back, own terms are not offered.
    app(Features::class)->setSite(Feature::SchoolEventCategories, false, false);
    $taxonomy->forget();
    expect($taxonomy->keys(TaxonomyKind::EventCategory, $this->school->fresh()))->toContain('sport')->not->toContain($own->key);
    Livewire::test(Manage::class)->call('startCreate')->set('name', 'Nowa')->set('icon', 'tag')->call('save')->assertForbidden();
});

it('validates event categories and group types against the school list', function () {
    $this->actingAs(userWithRole(Role::Coordinator));
    term('ceremony')->update(['is_active' => false]);

    Livewire::test(EventEdit::class)
        ->set('title', 'Akademia')->set('date', now()->addDay()->format('Y-m-d'))->set('start_time', '10:00')
        ->set('category', 'ceremony')->call('save')->assertHasErrors('category')
        ->set('category', 'not_a_category')->call('save')->assertHasErrors('category')
        ->set('category', 'competition')->call('save')->assertHasNoErrors();

    // An event keeps its archived category when edited.
    $old = Event::query()->create(['school_id' => $this->school->id, 'title' => 'Stare', 'category' => 'ceremony', 'visibility' => 'school_only', 'starts_at' => now()->addDays(2)]);
    Livewire::test(EventEdit::class, ['event' => $old])->set('title', 'Stare (zmiana)')->call('save')->assertHasNoErrors();
    expect($old->fresh()->category->key)->toBe('ceremony');

    $this->actingAs(admin());
    Livewire::test(GroupEdit::class)->set('name', 'Koło szachowe')->set('type', 'club')->call('save')->assertHasNoErrors()
        ->set('type', 'nope')->call('save')->assertHasErrors('type');
});

it('protects both category screens', function () {
    $this->actingAs(userWithRole(Role::Teacher))->get(route('admin.school.catalog'))->assertForbidden();
    $this->actingAs(admin())->get(route('site.catalog'))->assertForbidden();
    $this->actingAs(admin())->get(route('admin.school.catalog'))->assertOk()->assertSee('Dzień otwarty');
    $this->actingAs(siteAdmin())->get(route('site.catalog').'?lista=group_type')->assertOk()->assertSee('Koło / klub');
});

it('keeps the catch-all entry and features chosen categories on the public front', function () {
    $this->actingAs(siteAdmin());
    $other = term('other');

    Livewire::test(Manage::class, ['scope' => 'site'])->call('toggleActive', $other->id)->assertHasErrors('term');
    Livewire::test(Manage::class, ['scope' => 'site'])->call('delete', $other->id)->assertHasErrors('term');
    expect($other->fresh()->is_active)->toBeTrue();

    $this->actingAs(admin());
    Livewire::test(Manage::class)->call('toggleHidden', $other->id)->assertHasErrors('term');

    // Open days are featured by default; the nearest open day goes to the large tile even if a sport event is sooner.
    expect(term('open_day')->is_featured)->toBeTrue()->and(term('sport')->is_featured)->toBeFalse();
    foreach ([['Bieg', 'sport', 1], ['Dzień otwarty ZSM', 'open_day', 4]] as [$title, $cat, $days]) {
        Event::query()->create(['school_id' => $this->school->id, 'title' => $title, 'category' => $cat, 'visibility' => 'public', 'starts_at' => now()->addDays($days)->setTime(10, 0)]);
    }
    $list = Livewire::test(Home::class)->viewData('list');
    expect($list->first()->title)->toBe('Dzień otwarty ZSM');

    $this->actingAs(siteAdmin());
    Livewire::test(Manage::class, ['scope' => 'site'])->call('toggleFeatured', term('open_day')->id);
    expect(term('open_day')->is_featured)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'catalog.unfeatured')->exists())->toBeTrue();
    app(Taxonomy::class)->forget();
    expect(Livewire::test(Home::class)->viewData('list')->first()->title)->toBe('Bieg');
});
