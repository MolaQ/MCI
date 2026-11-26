<?php

use App\Domain\Projects\Actions\ChangeProjectStatus;
use App\Domain\Projects\Actions\LinkWork;
use App\Domain\Projects\Actions\ManageMilestones;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Tasks\Actions\SaveTask;
use App\Enums\Feature;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Livewire\Projects\Edit;
use App\Livewire\Projects\Index;
use App\Livewire\Projects\Show;
use App\Livewire\Tasks\Edit as TaskEdit;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Project;
use App\Models\School;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectNotification;
use App\Support\Features;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function project(User $actor, array $data = [], array $members = [], array $partners = [], ?School $school = null): Project
{
    $school ??= test()->school;
    actAsSchool($school, $actor);

    return app(SaveProject::class)->handle($actor, $school, $data + ['name' => 'Dzień Otwarty', 'kind' => 'open_day', 'coordinator_id' => $actor->id], $members, $partners);
}

it('creates a project with a team, tells the team and logs it', function () {
    Notification::fake();
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    $member = userWithRole(Role::Teacher);
    $group = Group::factory()->create(['school_id' => $this->school->id]);
    $group->members()->attach($member->id, ['role' => 'member']);

    $p = project($coordinator, ['status' => 'in_progress', 'starts_on' => '2027-01-10', 'ends_on' => '2027-03-01', 'budget_notes' => '2000 zł'], ['user:'.$teacher->id, 'group:'.$group->id]);

    expect($p->status)->toBe(ProjectStatus::InProgress)
        ->and($p->members()->count())->toBe(2)
        ->and($p->teamIds()->sort()->values()->all())->toBe(collect([$coordinator->id, $teacher->id, $member->id])->sort()->values()->all())
        ->and($p->datesLabel())->toContain('2027');
    Notification::assertSentTo([$teacher, $member], ProjectNotification::class, fn ($n) => $n->kind === 'assigned');
    Notification::assertNotSentTo($coordinator, ProjectNotification::class);
    expect(AuditLog::query()->where('action', 'project.created')->where('school_id', $this->school->id)->exists())->toBeTrue();

    // Dates in the wrong order and people from outside are refused.
    expect(fn () => project($coordinator, ['starts_on' => '2027-03-01', 'ends_on' => '2027-01-01']))->toThrow(ValidationException::class);
    $stranger = User::factory()->create();
    expect(fn () => project($coordinator, [], ['user:'.$stranger->id]))->toThrow(ValidationException::class);
    // Whole roles only for people who manage projects.
    expect(fn () => project($teacher, [], ['role:teacher']))->toThrow(ValidationException::class);
});

it('respects visibility: school-wide, team only, and managers', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    $member = userWithRole(Role::Teacher);
    $open = project($teacher, ['name' => 'Konkurs CAD']);
    $secret = project($teacher, ['name' => 'Grant', 'visibility' => 'team'], ['user:'.$member->id]);
    $outsider = userWithRole(Role::Teacher);
    $student = userWithRole(Role::Student);

    actAsSchool($this->school, $outsider);
    expect($outsider->can('view', $open))->toBeTrue()
        ->and($outsider->can('view', $secret))->toBeFalse()
        ->and($outsider->can('update', $open))->toBeFalse()
        ->and($member->can('view', $secret))->toBeTrue()
        ->and($member->can('work', $secret))->toBeTrue()
        ->and($member->can('update', $secret))->toBeFalse();
    actAsSchool($this->school, $coordinator);
    expect($coordinator->can('view', $secret))->toBeTrue()->and($coordinator->can('update', $secret))->toBeTrue();

    // Students may not create projects; teachers may.
    actAsSchool($this->school, $student);
    expect($student->can('create', Project::class))->toBeFalse();
    actAsSchool($this->school, $teacher);
    expect($teacher->can('create', Project::class))->toBeTrue();

    $this->actingAs($member);
    actAsSchool($this->school, $member);
    Livewire::test(Index::class)->assertSet('view', 'mine')->assertSee('Grant')->assertDontSee('Konkurs CAD')
        ->call('setFilter', 'view', 'all')->assertSee('Konkurs CAD')
        ->call('setFilter', 'status', 'completed')->assertDontSee('Grant');
    $this->get(route('projects.show', $secret))->assertOk()->assertSee('Grant');
    $this->actingAs($outsider)->get(route('projects.show', $secret))->assertForbidden();
});

it('shares a project with a partner school and logs it in both schools and the site journal', function () {
    $partner = School::factory()->create(['short_name' => 'LO2', 'slug' => 'lo2']);
    $coordinator = userWithRole(Role::Coordinator);
    actAsSchool($partner);
    $guest = userWithRole(Role::Teacher, [], $partner);

    $p = project($coordinator, ['name' => 'Erasmus+'], ['user:'.$guest->id], [$partner->id]);
    expect($p->schoolIds())->toBe([$this->school->id, $partner->id])
        ->and($p->members()->first()->school_id)->toBe($partner->id)
        ->and(AuditLog::query()->where('action', 'project.created')->pluck('school_id')->all())->toEqualCanonicalizing([$this->school->id, $partner->id, null]);

    actAsSchool($partner, $guest);
    expect($guest->can('view', $p))->toBeTrue()->and($guest->can('work', $p))->toBeTrue();

    app(Features::class)->setForSchool($this->school, Feature::ProjectCrossSchool, false);
    expect(fn () => project($coordinator, ['name' => 'Bez partnerów'], [], [$partner->id]))->toThrow(ValidationException::class);
});

it('tracks milestones, progress and reminders once per deadline', function () {
    Notification::fake();
    $teacher = userWithRole(Role::Teacher);
    $member = userWithRole(Role::Teacher);
    $outsider = userWithRole(Role::Teacher);
    $p = project($teacher, [], ['user:'.$member->id]);
    $ms = app(ManageMilestones::class);

    $soon = $ms->save($teacher, $p, ['title' => 'Ulotki', 'due_date' => now()->addHours(5)->format('Y-m-d'), 'due_time' => now()->addHours(5)->format('H:i')]);
    $late = $ms->save($teacher, $p, ['title' => 'Plan', 'due_date' => now()->subDay()->format('Y-m-d')]);
    expect($p->progress()['percent'])->toBe(0);

    $ms->toggle($member, $late);
    expect($late->fresh()->isDone())->toBeTrue()->and($p->fresh()->progress())->toMatchArray(['percent' => 50]);
    expect(fn () => $ms->toggle($outsider, $soon))->toThrow(AuthorizationException::class);
    expect(fn () => $ms->save($member, $p, ['title' => 'Nie wolno']))->toThrow(AuthorizationException::class);

    $ms->toggle($member, $late); // reopened → overdue
    $this->travelTo(now()->setTime(12, 0));
    $this->artisan('mci:project-reminders')->assertSuccessful();
    $this->artisan('mci:project-reminders')->assertSuccessful();
    $milestoneNotices = fn () => Notification::sent($member, ProjectNotification::class)->filter(fn ($n) => str_starts_with($n->kind, 'milestone_'))->count();
    expect($milestoneNotices())->toBe(2);
    Notification::assertSentTo($member, ProjectNotification::class, fn ($n) => $n->kind === 'milestone_due' && $n->milestone->is($soon));
    Notification::assertSentTo($teacher, ProjectNotification::class, fn ($n) => $n->kind === 'milestone_overdue' && $n->milestone->is($late));

    // A new deadline earns a new reminder.
    $ms->save($teacher, $p, ['title' => 'Ulotki', 'due_date' => now()->addHours(3)->format('Y-m-d'), 'due_time' => now()->addHours(3)->format('H:i')], $soon);
    expect($soon->fresh()->reminded_at)->toBeNull();

    // Switched off per school: nothing is sent.
    app(Features::class)->setForSchool($this->school, Feature::ProjectReminders, false);
    $this->artisan('mci:project-reminders')->assertSuccessful();
    expect($milestoneNotices())->toBe(2);
});

it('links tasks and workflow requests, and creates a task inside a project', function () {
    $teacher = userWithRole(Role::Coordinator);
    $member = userWithRole(Role::Teacher);
    $p = project($teacher, [], ['user:'.$member->id]);
    $m = app(ManageMilestones::class)->save($teacher, $p, ['title' => 'Etap 1']);
    actAsSchool($this->school, $teacher);
    $task = app(SaveTask::class)->handle($teacher, $this->school, ['title' => 'Plakaty'], ['user:'.$member->id]);
    $personal = app(SaveTask::class)->handle($teacher, $this->school, ['title' => 'Moje']);

    app(LinkWork::class)->task($teacher, $p, $task, $m->id);
    expect($task->fresh()->project_id)->toBe($p->id)->and($task->fresh()->project_milestone_id)->toBe($m->id);
    expect(fn () => app(LinkWork::class)->task($teacher, $p, $personal))->toThrow(ValidationException::class);

    $task->update(['status' => TaskStatus::Done]);
    expect($p->fresh()->progress()['tasks'])->toBe(['done' => 1, 'total' => 1]);

    // "Nowe zadanie w projekcie" from the project screen.
    $this->actingAs($teacher);
    Livewire::withQueryParams(['projekt' => $p->id])->test(TaskEdit::class)
        ->assertSee('Zadanie trafi do projektu')
        ->set('title', 'Nagłośnienie')->set('assignees', ['user:'.$member->id])
        ->call('save')->assertRedirect(route('projects.show', $p));
    expect(Task::query()->where('title', 'Nagłośnienie')->value('project_id'))->toBe($p->id);

    Livewire::test(Show::class, ['project' => $p])
        ->assertSee('Plakaty')->assertSee('Nagłośnienie')
        ->call('unlinkTask', $task->id);
    expect($task->fresh()->project_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'project.linked')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'project.unlinked')->count())->toBe(1);
});

it('works end to end through the screens and follows the module switch', function () {
    Notification::fake();
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    $this->actingAs($coordinator);
    actAsSchool($this->school, $coordinator);

    Livewire::test(Edit::class)
        ->set('name', 'Turniej siatkówki')->call('$set', 'kind', 'competition')
        ->set('peopleSearch', mb_substr($teacher->last_name, 0, 4))
        ->call('addPerson', $teacher->id)
        ->set('starts_on', now()->toDateString())
        ->call('save')->assertHasNoErrors();
    $p = Project::query()->where('name', 'Turniej siatkówki')->firstOrFail();
    expect($p->members()->first()->user_id)->toBe($teacher->id);

    Livewire::test(Show::class, ['project' => $p])
        ->set('milestoneTitle', 'Zapisy drużyn')->set('milestoneDate', now()->addWeek()->toDateString())
        ->call('saveMilestone')->assertHasNoErrors()->assertSee('Zapisy drużyn')
        ->call('setStatus', 'in_progress')->assertSee('W toku');
    expect($p->fresh()->status)->toBe(ProjectStatus::InProgress)
        ->and(AuditLog::query()->where('action', 'project.status')->exists())->toBeTrue();
    Notification::assertSentTo($teacher, ProjectNotification::class, fn ($n) => $n->kind === 'status');

    $this->get(route('projects.index'))->assertOk()->assertSee('Turniej siatkówki');
    $this->get(route('dashboard'))->assertSee(route('projects.index'), false);

    app(Features::class)->setForSchool($this->school, Feature::Projects, false);
    $this->get(route('projects.index'))->assertNotFound();
    $this->get(route('dashboard'))->assertDontSee(route('projects.index'), false);
    expect(app(ChangeProjectStatus::class))->toBeInstanceOf(ChangeProjectStatus::class);
});
