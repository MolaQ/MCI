<?php

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Enums\Feature;
use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Livewire\Publicly\Home;
use App\Models\School;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Support\Features;
use Livewire\Livewire;

function announce(User $author, School $school, string $title, bool $public): void
{
    actAsSchool($school, $author);
    app(PublishAnnouncement::class)->handle($author, $school, ['title' => $title, 'is_public' => $public, 'body' => '<p>Treść</p>'], ['everyone']);
}

it('features the only school straight away for guests, with its public announcements only', function () {
    $author = userWithRole(Role::Coordinator);
    userWithRole(Role::Teacher);
    announce($author, $this->school, 'Zebranie z rodzicami', true);
    announce($author, $this->school, 'Rada pedagogiczna', false);
    $this->school->offers()->create(['kind' => 'technikum', 'name' => 'Technik mechatronik', 'is_active' => true]);
    auth()->logout();

    $this->get(route('home'))->assertOk()
        ->assertSee($this->school->name)
        ->assertSee('Zebranie z rodzicami')->assertDontSee('Rada pedagogiczna')
        ->assertDontSee('data-filter="school"', false)
        ->assertSee('data-offer-block', false)->assertSee('Technik mechatronik');

    // The offer block follows the school's "public offers" switch.
    app(Features::class)->setForSchool($this->school, Feature::PublicOffers, false);
    $this->get(route('home'))->assertDontSee('data-offer-block', false)->assertDontSee('Technik mechatronik');
    $this->get(route('public.school', $this->school->slug))->assertOk()->assertDontSee('Technik mechatronik');
});

it('shows public announcements of every school to guests when there are several', function () {
    $partner = School::factory()->create(['short_name' => 'LO2', 'slug' => 'lo2', 'name' => 'II Liceum']);
    announce(userWithRole(Role::Coordinator), $this->school, 'Dni otwarte ZSM', true);
    actAsSchool($partner);
    $partnerAuthor = userWithRole(Role::Coordinator, [], $partner);
    userWithRole(Role::Teacher, [], $partner);
    announce($partnerAuthor, $partner, 'Matura próbna LO2', true);
    announce($partnerAuthor, $partner, 'Wewnętrzna narada LO2', false);
    auth()->logout();

    $this->get(route('home'))->assertOk()
        ->assertSee('data-filter="school"', false)
        ->assertSee('Dni otwarte ZSM')->assertSee('Matura próbna LO2')->assertDontSee('Wewnętrzna narada LO2')
        ->assertDontSee('data-offer-block', false);

    Livewire::test(Home::class)->call('setFilter', 'school', 'lo2')
        ->assertSee('Matura próbna LO2')->assertDontSee('Dni otwarte ZSM');
});

it('sends signed-in people to their school dashboard and keeps a public preview', function () {
    $teacher = userWithRole(Role::Teacher);

    $this->actingAs($teacher)->get(route('home'))->assertRedirect(route('dashboard'));
    $this->actingAs($teacher)->get(route('home', ['podglad' => 1]))->assertOk()->assertSee('Podgląd publiczny');
    $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee(route('home', ['podglad' => 1]), false);
});

it('puts the internal content of the current school first on the dashboard', function () {
    $partner = School::factory()->create(['short_name' => 'LO2', 'slug' => 'lo2']);
    $teacher = userWithRole(Role::Teacher);
    $teacher->schools()->attach($partner->id, ['status' => 'active']);
    $author = userWithRole(Role::Coordinator);
    announce($author, $this->school, 'Rada pedagogiczna', false);
    actAsSchool($partner);
    $teacher->assignRole(Role::Teacher->value);
    announce(userWithRole(Role::Coordinator, [], $partner), $partner, 'Sprawy LO2', false);

    $request = WorkflowRequest::query()->create([
        'school_id' => $this->school->id, 'requester_id' => $author->id, 'title' => 'Wycieczka 3a', 'template_name' => 'Wycieczka',
        'data' => [], 'stages' => [['name' => 'Dyrektor']], 'status' => 'in_progress', 'current_position' => 0,
        'approver_type' => 'user', 'approver_id' => (string) $teacher->id, 'approver_school_id' => $this->school->id, 'submitted_at' => now(),
    ]);

    actAsSchool($this->school, $teacher);
    $this->actingAs($teacher);
    $c = Livewire::test(Dashboard::class)
        ->assertSee('Rada pedagogiczna')->assertDontSee('Sprawy LO2')
        ->assertSee('Wycieczka 3a')->assertSee('Do mojej decyzji');
    expect($c->instance()->summary)->toMatchArray(['unread' => 1, 'decisions' => 1]);

    // Switched-off modules disappear from the dashboard.
    app(Features::class)->setForSchool($this->school, Feature::Workflows, false);
    Livewire::test(Dashboard::class)->assertDontSee('Wycieczka 3a')->assertDontSee('data-dashboard-decisions', false);
    expect($request->fresh())->not->toBeNull();
});
