<?php

use App\Domain\Taxonomy\Actions\DeleteTerm;
use App\Domain\Taxonomy\Actions\HideTermInSchool;
use App\Domain\Taxonomy\Actions\MoveTerm;
use App\Domain\Taxonomy\Actions\SaveTerm;
use App\Domain\Taxonomy\TermCatalog;
use App\Enums\Feature;
use App\Enums\Role;
use App\Enums\TermKind;
use App\Livewire\Events\Edit as EventEdit;
use App\Livewire\Groups\Edit as GroupEdit;
use App\Livewire\Taxonomy\Manage;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Group;
use App\Models\School;
use App\Models\Term;
use App\Support\Features;
use Database\Seeders\SystemGroupsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function termCatalog(): TermCatalog
{
    return app(TermCatalog::class);
}

function schoolTerm(TermKind $kind, string $name, ?School $school = null): Term
{
    $school ??= test()->school;

    return Term::query()->create(['kind' => $kind, 'school_id' => $school->id, 'key' => str($name)->slug('_'), 'name' => $name, 'icon' => 'tag', 'position' => 0]);
}

it('turns the old fixed categories and group types into editable portal-wide entries', function () {
    expect(Term::query()->ofKind(TermKind::EventCategory)->whereNull('school_id')->pluck('key')->all())
        ->toBe(['open_day', 'competition', 'recruitment', 'ceremony', 'sport', 'charity', 'meeting', 'other'])
        ->and(Term::query()->ofKind(TermKind::GroupType)->whereNull('school_id')->count())->toBe(10)
        ->and(Term::query()->where('key', 'open_day')->value('is_featured'))->toBeTrue();

    SystemGroupsSeeder::seedFor($this->school);
    $tutors = Group::query()->where('school_id', $this->school->id)->where('slug', 'wychowawcy')->sole();
    expect($tutors->type->label())->toBe('Wychowawcy')
        ->and($tutors->displayColor())->toBe('#7c3aed');
});

it('lets site admins manage portal-wide entries, logged in the site journal', function () {
    $this->actingAs(siteAdmin());

    Livewire::test(Manage::class, ['scope' => 'site'])
        ->call('startCreate')
        ->set('name', 'Wycieczka')->call('pickIcon', 'map')->set('is_featured', true)
        ->call('save')->assertHasNoErrors();

    $term = Term::query()->where('name', 'Wycieczka')->sole();
    expect($term->school_id)->toBeNull()->and($term->icon)->toBe('map')->and($term->is_featured)->toBeTrue()
        ->and($term->kind)->toBe(TermKind::EventCategory);
    expect(AuditLog::query()->where('action', 'term.created')->whereNull('school_id')->exists())->toBeTrue();

    Livewire::test(Manage::class, ['scope' => 'site'])
        ->call('startEdit', categoryId('sport'))->set('name', 'Sport i rekreacja')->call('save')->assertHasNoErrors();
    expect(Term::query()->find(categoryId('sport'))->name)->toBe('Sport i rekreacja')
        ->and(AuditLog::query()->where('action', 'term.updated')->whereNull('school_id')->exists())->toBeTrue();

    $this->actingAs(admin())->get(route('site.terms'))->assertForbidden();
});

it('keeps school managers inside their own school', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $this->actingAs($coordinator)->get(route('admin.terms'))->assertOk()->assertSee('Kategorie i typy grup');
    $this->actingAs(userWithRole(Role::Teacher))->get(route('admin.terms'))->assertForbidden();
    $this->actingAs($coordinator);

    Livewire::test(Manage::class)->call('setKind', 'group_type')
        ->call('startCreate')->set('name', 'Samorząd uczniowski')->call('pickIcon', 'megaphone')->set('color', '#0f766e')
        ->call('save')->assertHasNoErrors();

    $own = Term::query()->where('name', 'Samorząd uczniowski')->sole();
    expect($own->school_id)->toBe($this->school->id)->and($own->kind)->toBe(TermKind::GroupType)->and($own->color)->toBe('#0f766e');
    expect(AuditLog::query()->where('action', 'term.created')->where('school_id', $this->school->id)->exists())->toBeTrue();

    // Portal-wide entries are the site admin's; another school's entries are out of reach.
    Livewire::test(Manage::class)->call('startEdit', categoryId('sport'))->assertForbidden();
    $foreign = schoolTerm(TermKind::EventCategory, 'Obca kategoria', School::factory()->create());
    Livewire::test(Manage::class)->call('startEdit', $foreign->id)->assertNotFound();
    expect(fn () => app(SaveTerm::class)->handle($coordinator, TermKind::EventCategory, null, ['name' => 'Ogólna', 'icon' => 'tag']))
        ->toThrow(AuthorizationException::class);
});

it('lets a school use only portal-wide entries when its feature switch is off', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $own = schoolTerm(TermKind::EventCategory, 'Apel');
    app(HideTermInSchool::class)->handle($coordinator, Term::query()->find(categoryId('sport')), $this->school, true);

    expect(termCatalog()->forSchool(TermKind::EventCategory, $this->school)->pluck('name'))->toContain('Apel')->not->toContain('Sport');

    app(Features::class)->setForSchool($this->school, Feature::EventCategoriesCustom, false);
    $school = $this->school->fresh();
    actAsSchool($school, $coordinator);

    expect(termCatalog()->forSchool(TermKind::EventCategory, $school)->pluck('name'))->not->toContain('Apel')->toContain('Sport');
    expect($coordinator->can('create', [Term::class, TermKind::EventCategory, $school]))->toBeFalse();

    $this->actingAs($coordinator);
    Livewire::test(EventEdit::class)
        ->set('title', 'Apel poranny')->set('category_id', $own->id)
        ->set('date', now()->addDay()->format('Y-m-d'))->set('start_time', '08:00')
        ->call('save')->assertHasErrors('category_id');
});

it('hides portal-wide entries per school but never the fallback', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $other = School::factory()->create();
    $this->actingAs($coordinator);

    Livewire::test(Manage::class)->call('toggleHidden', categoryId('charity'))->assertHasNoErrors();

    expect(termCatalog()->forSchool(TermKind::EventCategory, $this->school->fresh())->pluck('key'))->not->toContain('charity')
        ->and(termCatalog()->forSchool(TermKind::EventCategory, $other)->pluck('key'))->toContain('charity')
        ->and(AuditLog::query()->where('action', 'term.hidden')->where('school_id', $this->school->id)->exists())->toBeTrue();

    Livewire::test(Manage::class)->call('toggleHidden', categoryId('other'))->assertForbidden();
    expect(termCatalog()->forSchool(TermKind::EventCategory, $this->school->fresh())->pluck('key'))->toContain('other');

    Livewire::test(Manage::class)->call('toggleHidden', categoryId('charity'));
    expect(termCatalog()->forSchool(TermKind::EventCategory, $this->school->fresh())->pluck('key'))->toContain('charity');
});

it('archives entries that are in use and deletes only unused ones', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $this->actingAs($coordinator);
    $used = schoolTerm(TermKind::EventCategory, 'Próbna ewakuacja');
    $unused = schoolTerm(TermKind::EventCategory, 'Do usunięcia');
    $event = Event::query()->create(['school_id' => $this->school->id, 'owner_id' => $coordinator->id, 'title' => 'Ewakuacja', 'category_id' => $used->id, 'visibility' => 'school_only', 'starts_at' => now()->addDay()]);

    expect(fn () => app(DeleteTerm::class)->handle($coordinator, $used))->toThrow(ValidationException::class);
    expect($used->fresh())->not->toBeNull();

    Livewire::test(Manage::class)->call('toggleActive', $used->id);
    expect($used->fresh()->is_active)->toBeFalse()
        ->and(termCatalog()->forSchool(TermKind::EventCategory, $this->school)->pluck('id'))->not->toContain($used->id);

    // The event keeps its archived category, and its form still offers it.
    Livewire::test(EventEdit::class, ['event' => $event])
        ->assertViewHas('categories', fn ($c) => $c->pluck('id')->contains($used->id))
        ->call('save')->assertHasNoErrors();
    expect($event->fresh()->category->name)->toBe('Próbna ewakuacja');

    Livewire::test(Manage::class)->call('delete', $unused->id)->assertHasNoErrors();
    expect(Term::query()->find($unused->id))->toBeNull()
        ->and(AuditLog::query()->where('action', 'term.deleted')->where('school_id', $this->school->id)->exists())->toBeTrue();

    // The fallback entry can never be archived or deleted, not even by a site admin.
    $site = siteAdmin();
    expect(fn () => app(DeleteTerm::class)->handle($site, Term::query()->find(categoryId('other'))))->toThrow(AuthorizationException::class);
});

it('reorders an entry within its own list', function () {
    $site = siteAdmin();
    app(MoveTerm::class)->handle($site, Term::query()->find(categoryId('competition')), 'up');

    expect(Term::query()->ofKind(TermKind::EventCategory)->whereNull('school_id')->ordered()->pluck('key')->take(2)->all())->toBe(['competition', 'open_day'])
        ->and(AuditLog::query()->where('action', 'term.reordered')->exists())->toBeTrue();
});

it('validates names, icons and colours', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $save = fn (array $input, TermKind $kind = TermKind::EventCategory) => app(SaveTerm::class)->handle($coordinator, $kind, $this->school, $input);

    expect(fn () => $save(['name' => 'sport', 'icon' => 'flag']))->toThrow(ValidationException::class)
        ->and(fn () => $save(['name' => 'Nowa', 'icon' => 'not-an-icon']))->toThrow(ValidationException::class)
        ->and(fn () => $save(['name' => 'Nowy typ', 'icon' => 'tag', 'color' => 'red;}'], TermKind::GroupType))->toThrow(ValidationException::class)
        ->and(fn () => $save(['name' => 'X', 'icon' => 'tag']))->toThrow(ValidationException::class);

    // Colour is ignored for event categories; "featured" is ignored for group types.
    $term = $save(['name' => '  Dzień   sportu ', 'icon' => 'flag', 'color' => '#ff0000']);
    expect($term->name)->toBe('Dzień sportu')->and($term->color)->toBeNull();
});

it('offers groups only the types of their own school', function () {
    $this->actingAs(userWithRole(Role::Coordinator));
    $foreign = schoolTerm(TermKind::GroupType, 'Obcy typ', School::factory()->create());
    $own = schoolTerm(TermKind::GroupType, 'Koło robotyki');

    Livewire::test(GroupEdit::class)->set('name', 'Robotycy')->set('type_id', $foreign->id)->call('save')->assertHasErrors('type_id');
    Livewire::test(GroupEdit::class)->set('name', 'Robotycy')->set('type_id', $own->id)->call('save')->assertHasNoErrors();

    expect(Group::query()->where('name', 'Robotycy')->sole()->type->name)->toBe('Koło robotyki');
});
