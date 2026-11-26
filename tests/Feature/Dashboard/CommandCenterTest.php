<?php

use App\Domain\Calendar\Agenda;
use App\Domain\Projects\Actions\ManageMilestones;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Tasks\Actions\SaveTask;
use App\Enums\Feature;
use App\Enums\Role;
use App\Livewire\Admin\TvSettings;
use App\Livewire\Dashboard;
use App\Livewire\Publicly\Board;
use App\Livewire\Publicly\Home;
use App\Livewire\Settings\Profile;
use App\Models\Event;
use App\Models\Project;
use App\Models\School;
use App\Support\Features;
use App\Support\TvScreens;
use Livewire\Livewire;

function ccEvent(string $title, array $attributes = []): Event
{
    return Event::query()->create($attributes + [
        'school_id' => test()->school->id, 'title' => $title, 'category' => 'open_day',
        'starts_at' => now()->setTime(18, 0), 'ends_at' => now()->setTime(19, 0), 'visibility' => 'public',
    ]);
}

function ccProject(string $name, string $visibility, array $milestones = []): Project
{
    $coordinator = userWithRole(Role::Coordinator);
    actAsSchool(test()->school, $coordinator);
    $project = app(SaveProject::class)->handle($coordinator, test()->school, [
        'name' => $name, 'kind' => 'competition', 'status' => 'in_progress', 'visibility' => $visibility,
        'starts_on' => now()->subWeek()->toDateString(), 'ends_on' => now()->addWeeks(4)->toDateString(), 'coordinator_id' => $coordinator->id,
    ]);
    foreach ($milestones as $title => $days) {
        app(ManageMilestones::class)->save($coordinator, $project, ['title' => $title, 'due_date' => now()->addDays($days)->toDateString()]);
    }

    return $project;
}

it('builds today, the month and the project timeline on the dashboard in personal and school views', function () {
    $teacher = userWithRole(Role::Teacher);
    $coordinator = userWithRole(Role::Coordinator);
    ccEvent('Konsultacje dla rodziców');
    ccEvent('Rada pedagogiczna', ['visibility' => 'school_only', 'starts_at' => now()->setTime(15, 0), 'ends_at' => now()->setTime(17, 0)]);
    actAsSchool($this->school, $coordinator);
    app(SaveTask::class)->handle($coordinator, $this->school, ['title' => 'Oddać dziennik', 'due_date' => now()->toDateString(), 'due_time' => '23:00'], ['user:'.$teacher->id]);
    ccProject('Konkurs robotyki', 'school', ['Finał konkursu' => 2]);

    actAsSchool($this->school, $teacher);
    $this->actingAs($teacher);
    $c = Livewire::test(Dashboard::class);

    expect($c->instance()->mode)->toBe('personal')
        ->and($c->instance()->today->pluck('title')->all())->toBe(['Rada pedagogiczna', 'Konsultacje dla rodziców', 'Oddać dziennik']);
    $c->assertSee('Agenda dnia')->assertSee('data-dashboard-calendar', false)->assertSee('Projekty w czasie');
    // Personal view: the teacher is not in the project team.
    expect(collect($c->instance()->monthDays)->flatten()->pluck('title'))->not->toContain('Finał konkursu')
        ->and($c->instance()->timeline)->toBeEmpty();

    // School view: every visible project and its milestones, no personal tasks.
    $c->call('setCalendar', 'school');
    $titles = collect($c->instance()->monthDays)->flatten()->pluck('title');
    if (now()->addDays(2)->isSameMonth(now())) {
        expect($titles)->toContain('Finał konkursu');
    }
    expect($titles)->not->toContain('Oddać dziennik')
        ->and($c->instance()->timeline->pluck('name')->all())->toBe(['Konkurs robotyki']);

    // Month navigation stays within the URL.
    $c->call('shiftMonth', 1)->assertSet('month', now()->addMonthNoOverflow()->format('Y-m'))->call('shiftMonth', 0)->assertSet('month', '');

    // Switched-off modules leave the calendar.
    app(Features::class)->setForSchool($this->school, Feature::Projects, false);
    Livewire::test(Dashboard::class)->assertDontSee('data-dashboard-timeline', false);
});

it('remembers the default calendar view: person, then school, then site', function () {
    $teacher = userWithRole(Role::Teacher);
    actAsSchool($this->school, $teacher);
    $this->actingAs($teacher);

    expect(Agenda::defaultMode($teacher, $this->school))->toBe('personal');
    $this->school->update(['settings' => array_merge($this->school->settings ?? [], ['calendar_default' => 'school'])]);
    expect(Agenda::defaultMode($teacher->fresh(), $this->school->fresh()))->toBe('school');

    Livewire::test(Dashboard::class)->call('setCalendar', 'personal')->call('rememberCalendar');
    expect($teacher->fresh()->notification_prefs['calendar'])->toBe('personal')
        ->and(Agenda::defaultMode($teacher->fresh(), $this->school->fresh()))->toBe('personal');

    Livewire::test(Profile::class)->set('calendar', '');
    expect($teacher->fresh()->notification_prefs)->not->toHaveKey('calendar');
});

it('shows public projects and today on the public front, never internal ones', function () {
    ccEvent('Dzień sportu');
    ccEvent('Narada', ['visibility' => 'school_only']);
    ccProject('Paczka świąteczna', 'public', ['Zbiórka darów' => 1]);
    ccProject('Remont auli', 'school');
    auth()->logout();

    $c = Livewire::test(Home::class);
    expect($c->instance()->today->pluck('title')->all())->toBe(['Dzień sportu'])
        ->and($c->instance()->timeline->pluck('name')->all())->toBe(['Paczka świąteczna']);
    $c->assertSee('Paczka świąteczna')->assertDontSee('Remont auli')->assertDontSee('Narada')->assertSee('data-public-calendar', false);

    // The public scope never exposes internal columns.
    expect(Project::query()->publiclyVisible()->first()->getAttributes())->not->toHaveKey('budget_notes');
});

it('rotates TV screens with seconds from site, school and the board link', function () {
    ccEvent('Dzień sportu');
    $tv = app(TvScreens::class);
    expect(collect($tv->resolve(null))->pluck('seconds', 'key')->all())->toBe(['agenda' => 20, 'calendar' => 20, 'projects' => 20, 'announcements' => 15]);

    $this->actingAs(siteAdmin());
    Livewire::test(TvSettings::class, ['scope' => 'site'])
        ->set('rows.0.seconds', 45)->set('rows.3.on', false)->call('move', 1, -1)->call('save')->assertHasNoErrors();
    expect($tv->resolve(null))->toBe([['key' => 'calendar', 'seconds' => 20], ['key' => 'agenda', 'seconds' => 45], ['key' => 'projects', 'seconds' => 20]]);

    Livewire::test(TvSettings::class, ['scope' => 'site'])->set('rows.0.seconds', 2)->call('save')->assertHasErrors('rows.0.seconds');

    // A school with its own rotation, and a board link overriding both.
    $admin = admin();
    actAsSchool($this->school, $admin);
    $this->actingAs($admin);
    Livewire::test(TvSettings::class)->set('inherit', false)->set('rows', [['key' => 'agenda', 'on' => true, 'seconds' => 12]])->call('save')->assertHasNoErrors();
    expect($tv->resolve($this->school->fresh())[0])->toBe(['key' => 'agenda', 'seconds' => 12])
        ->and($tv->resolve($this->school->fresh(), 'calendar:30,bogus:5,agenda'))->toBe([['key' => 'calendar', 'seconds' => 30], ['key' => 'agenda', 'seconds' => 12]]);

    // The board shows the configured screens and skips empty ones (no public projects here).
    auth()->logout();
    Livewire::test(Board::class)->set('screensParam', 'agenda:10,projects:10,calendar:10')
        ->assertViewHas('screens', [['key' => 'agenda', 'seconds' => 10], ['key' => 'calendar', 'seconds' => 10]])
        ->assertSee('Dzień sportu')->assertSee('data-tv-screen="calendar"', false)->assertDontSee('data-tv-screen="projects"', false);
    $this->get(route('tv', ['ekrany' => 'agenda:15,calendar:25']))->assertOk()->assertSee('Kalendarz miesiąca · 25 s', false);
});

it('keeps TV settings to site and school admins', function () {
    $this->actingAs(userWithRole(Role::Teacher))->get(route('admin.school.tv'))->assertForbidden();
    $this->actingAs(admin())->get(route('site.tv'))->assertForbidden();
    $this->actingAs(admin())->get(route('admin.school.tv'))->assertOk();
    $this->actingAs(siteAdmin())->get(route('site.tv'))->assertOk()->assertSee('Ekrany TV');
});

it('hides the school switcher when there is only one school', function () {
    $teacher = userWithRole(Role::Teacher);
    $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertDontSee(__('Zmień szkołę'));

    School::factory()->create(['short_name' => 'LO2']);
    $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee(__('Zmień szkołę'));
});
