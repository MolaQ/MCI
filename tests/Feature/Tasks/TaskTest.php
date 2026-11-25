<?php

use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Actions\SaveTask;
use App\Enums\Feature;
use App\Enums\GroupRole;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Livewire\Tasks\Edit;
use App\Livewire\Tasks\Index;
use App\Livewire\Tasks\Show;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\School;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use App\Support\Features;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function saveTask(User $actor, array $data = [], array $assignees = [], array $checklist = [], ?Task $task = null): Task
{
    actAsSchool(test()->school, $actor);

    return app(SaveTask::class)->handle($actor, test()->school, $data + ['title' => 'Zadanie'], $assignees, $checklist, [], [], $task);
}

function groupWith(User ...$members): Group
{
    $group = Group::factory()->create();
    foreach ($members as $m) {
        $group->members()->attach($m->id, ['role' => GroupRole::Member->value]);
    }

    return $group;
}

it('keeps a task without assignees personal and private', function () {
    $me = userWithRole(Role::Teacher);
    $boss = userWithRole(Role::Coordinator);
    $task = saveTask($me, ['title' => 'Sprawdzić klasówki'], ['user:'.$me->id]);

    expect($task->is_personal)->toBeTrue()
        ->and($task->assignees()->count())->toBe(0)
        ->and(Task::query()->assignedTo($me)->pluck('id')->all())->toBe([$task->id])
        ->and($me->can('view', $task))->toBeTrue()
        ->and($boss->can('view', $task))->toBeFalse();

    $this->actingAs($boss)->get(route('tasks.show', $task))->assertForbidden();
});

it('lets only managers and group leaders assign tasks to others', function () {
    $teacher = userWithRole(Role::Teacher);
    $leader = userWithRole(Role::Teacher);
    $member = userWithRole(Role::Teacher);
    $outsider = userWithRole(Role::Teacher);
    $team = groupWith($member);
    $team->members()->attach($leader->id, ['role' => GroupRole::Leader->value]);
    $foreign = groupWith($outsider);
    $otherSchoolUser = userWithRole(Role::Teacher, school: School::factory()->create());

    expect(fn () => saveTask($teacher, [], ['user:'.$member->id]))->toThrow(ValidationException::class);

    expect(saveTask($leader, [], ['group:'.$team->id, 'user:'.$member->id])->assignees()->count())->toBe(2)
        ->and(fn () => saveTask($leader, [], ['group:'.$foreign->id]))->toThrow(ValidationException::class)
        ->and(fn () => saveTask($leader, [], ['user:'.$outsider->id]))->toThrow(ValidationException::class)
        ->and(fn () => saveTask($leader, [], ['role:teacher']))->toThrow(ValidationException::class);

    $coordinator = userWithRole(Role::Coordinator);
    expect(saveTask($coordinator, [], ['role:teacher', 'group:'.$foreign->id])->is_personal)->toBeFalse()
        ->and(fn () => saveTask($coordinator, [], ['user:'.$otherSchoolUser->id]))->toThrow(ValidationException::class)
        ->and(fn () => saveTask($coordinator, [], ['role:admin']))->toThrow(ValidationException::class);

    // Team assignments can be switched off for the school.
    app(Features::class)->setForSchool($this->school, Feature::TaskTeams, false);
    app()->forgetScopedInstances();
    expect(fn () => saveTask($coordinator, [], ['group:'.$foreign->id]))->toThrow(ValidationException::class)
        ->and(saveTask($coordinator, [], ['user:'.$outsider->id])->assignees()->count())->toBe(1);
});

it('shows group and role tasks to every member, and one completion completes it', function () {
    Notification::fake();
    $coordinator = userWithRole(Role::Coordinator);
    $a = userWithRole(Role::Staff);
    $b = userWithRole(Role::Staff);
    $teacher = userWithRole(Role::Teacher);
    $bystander = userWithRole(Role::Staff);
    $team = groupWith($a, $b);

    $groupTask = saveTask($coordinator, ['title' => 'Dyżur'], ['group:'.$team->id]);
    $roleTask = saveTask($coordinator, ['title' => 'Oceny'], ['role:teacher']);

    expect(Task::query()->assignedTo($a)->pluck('id')->all())->toBe([$groupTask->id])
        ->and(Task::query()->assignedTo($teacher)->pluck('id')->all())->toBe([$roleTask->id])
        ->and(Task::query()->assignedTo($bystander)->count())->toBe(0)
        ->and(Task::query()->assignedBy($coordinator)->count())->toBe(2)
        ->and($bystander->can('view', $groupTask))->toBeFalse();

    Notification::assertSentTo([$a, $b], TaskNotification::class, fn ($n) => $n->kind === 'assigned');
    Notification::assertSentTo($teacher, TaskNotification::class);
    Notification::assertNotSentTo([$coordinator, $bystander], TaskNotification::class);

    actAsSchool($this->school, $b);
    app(ChangeTaskStatus::class)->handle($b, $groupTask, TaskStatus::Done);
    expect($groupTask->fresh()->status)->toBe(TaskStatus::Done)
        ->and($groupTask->fresh()->completed_by)->toBe($b->id)
        ->and(Task::query()->assignedTo($a)->open()->count())->toBe(0);
    Notification::assertSentTo($coordinator, TaskNotification::class, fn ($n) => $n->kind === 'completed');

    // Assignees may work on a task but not cancel it; outsiders may do neither.
    expect(fn () => app(ChangeTaskStatus::class)->handle($a, $groupTask, TaskStatus::Cancelled))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeTaskStatus::class)->handle($bystander, $roleTask, TaskStatus::Done))->toThrow(AuthorizationException::class);

    expect(AuditLog::query()->where('action', 'task.created')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'task.status')->where('subject_id', $groupTask->id)->exists())->toBeTrue();
});

it('notifies only new assignees and new mentions on edit', function () {
    Notification::fake();
    $coordinator = userWithRole(Role::Coordinator);
    $first = userWithRole(Role::Teacher);
    $second = userWithRole(Role::Teacher);
    $mentioned = userWithRole(Role::Teacher, ['first_name' => 'Ola', 'last_name' => 'Nowak']);
    $mention = '<p>Pomoże <span data-type="mention" data-id="user:'.$mentioned->id.'" data-label="X">@X</span></p>';

    $task = saveTask($coordinator, ['description' => $mention], ['user:'.$first->id]);
    expect($task->description)->toContain('@Ola Nowak')
        ->and($mentioned->can('view', $task))->toBeTrue();
    Notification::assertSentToTimes($first, TaskNotification::class, 1);
    Notification::assertSentTo($mentioned, TaskNotification::class, fn ($n) => $n->kind === 'mention');

    saveTask($coordinator, ['description' => $mention], ['user:'.$first->id, 'user:'.$second->id], task: $task);
    Notification::assertSentToTimes($first, TaskNotification::class, 1);
    Notification::assertSentToTimes($mentioned, TaskNotification::class, 1);
    Notification::assertSentToTimes($second, TaskNotification::class, 1);

    expect(AuditLog::query()->where('action', 'task.updated')->first()->properties['assignees_added'])->toBe(['user:'.$second->id]);
});

it('stores the due time in Polish time and validates the checklist', function () {
    $me = userWithRole(Role::Teacher);
    $task = saveTask($me, ['due_date' => '2026-12-01', 'due_time' => '08:30'], checklist: [
        ['body' => 'Kserówki', 'is_done' => true], ['body' => '  '], ['body' => 'Sala 12'],
    ]);

    expect($task->due_at->format('Y-m-d H:i'))->toBe('2026-12-01 08:30')
        ->and($task->due_at->timezone->getName())->toBe('Europe/Warsaw')
        ->and($task->all_day)->toBeFalse()
        ->and($task->checklist->pluck('body')->all())->toBe(['Kserówki', 'Sala 12'])
        ->and($task->checklistProgress())->toBe(['done' => 1, 'total' => 2]);

    $allDay = saveTask($me, ['due_date' => '2026-12-02']);
    expect($allDay->all_day)->toBeTrue()->and($allDay->due_at->format('H:i'))->toBe('23:59');

    expect(fn () => saveTask($me, ['due_date' => '01.12.2026']))->toThrow(ValidationException::class)
        ->and(fn () => saveTask($me, [], checklist: [['body' => str_repeat('x', 201)]]))->toThrow(ValidationException::class);
});

it('sends due-soon reminders once and overdue reminders once a day', function () {
    Notification::fake();
    $this->travelTo(now()->setTime(10, 0));
    $coordinator = userWithRole(Role::Coordinator);
    $a = userWithRole(Role::Staff);
    $team = groupWith($a, userWithRole(Role::Staff));

    $soon = saveTask($coordinator, ['due_date' => now()->toDateString(), 'due_time' => now()->addMinutes(30)->format('H:i')], ['group:'.$team->id]);
    $late = saveTask($a, ['due_date' => now()->subDay()->toDateString()]);
    $later = saveTask($a, ['due_date' => now()->addDays(5)->toDateString()]);
    Notification::fake();

    $this->artisan('mci:task-reminders')->assertSuccessful();
    $this->artisan('mci:task-reminders')->assertSuccessful();

    Notification::assertSentToTimes($a, TaskNotification::class, 2);
    Notification::assertSentTo($a, TaskNotification::class, fn ($n) => $n->kind === 'due_soon' && $n->task->is($soon));
    Notification::assertSentTo($a, TaskNotification::class, fn ($n) => $n->kind === 'overdue' && $n->task->is($late));
    Notification::assertNotSentTo($coordinator, TaskNotification::class);

    $this->travel(1)->day();
    $this->artisan('mci:task-reminders');
    Notification::assertSentToTimes($a, TaskNotification::class, 4); // the next day: overdue again for both

    app(Features::class)->setForSchool($this->school, Feature::TaskReminders, false);
    app()->forgetScopedInstances();
    $this->travel(1)->day();
    $this->artisan('mci:task-reminders');
    Notification::assertSentToTimes($a, TaskNotification::class, 4);
    expect($later->id)->toBeInt();
});

it('filters the list with pills and toggles status in one click', function () {
    $me = userWithRole(Role::Teacher);
    actAsSchool($this->school, $me);
    $this->actingAs($me);
    $overdue = saveTask($me, ['title' => 'Zaległe', 'due_date' => now()->subDays(2)->toDateString()]);
    $today = saveTask($me, ['title' => 'Na dziś', 'due_date' => now()->toDateString(), 'priority' => 4]);
    $someday = saveTask($me, ['title' => 'Kiedyś']);

    $list = Livewire::test(Index::class)
        ->assertSee('Zaległe')->assertSee('Na dziś')->assertSee('Kiedyś')
        ->assertSet('counts.overdue', 1)
        ->call('setFilter', 'when', 'overdue')->assertSee('Zaległe')->assertDontSee('Na dziś')
        ->call('setFilter', 'when', 'all')->call('setFilter', 'priority', '4')->assertSee('Na dziś')->assertDontSee('Kiedyś')
        ->call('setFilter', 'priority', '')
        ->call('cycle', $someday->id);
    expect($someday->fresh()->status)->toBe(TaskStatus::InProgress);

    $list->call('cycle', $someday->id)->assertDontSee('Kiedyś')->set('showDone', true)->assertSee('Kiedyś');
    expect($someday->fresh()->status)->toBe(TaskStatus::Done);

    $list->set('quickTitle', 'Szybkie')->set('quickDue', 'tomorrow')->call('quickAdd')->assertHasNoErrors();
    expect(Task::query()->where('title', 'Szybkie')->first()->due_at->isTomorrow())->toBeTrue();

    Livewire::test(Index::class)->call('setFilter', 'view', 'everything')->assertStatus(422);
});

it('creates and edits a task through the form', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    actAsSchool($this->school, $coordinator);
    $this->actingAs($coordinator);

    Livewire::test(Edit::class)
        ->set('title', 'Przygotować salę')
        ->set('priority', 3)
        ->set('due_date', '2026-12-10')->set('due_time', '14:00')
        ->call('addPerson', $teacher->id)
        ->set('newItem', 'Krzesła')->call('addItem')
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $task = Task::query()->where('title', 'Przygotować salę')->firstOrFail();
    expect($task->assignees()->first()->user_id)->toBe($teacher->id)
        ->and($task->checklist()->count())->toBe(1)
        ->and($task->priority->value)->toBe(3);

    Livewire::test(Edit::class, ['task' => $task])
        ->assertSet('due_time', '14:00')->assertSet('assignees', ['user:'.$teacher->id])
        ->call('onlyMe')->call('save')->assertHasNoErrors();
    expect($task->fresh()->is_personal)->toBeTrue();

    // The detail page: checklist ticks by the author.
    Livewire::test(Show::class, ['task' => $task])->call('toggleItem', $task->checklist()->first()->id);
    expect($task->checklist()->first()->is_done)->toBeTrue();

    $this->actingAs($teacher)->get(route('tasks.edit', $task))->assertForbidden();
});

it('hides the module when tasks are switched off', function () {
    $me = userWithRole(Role::Teacher);
    app(Features::class)->setForSchool($this->school, Feature::Tasks, false);
    app()->forgetScopedInstances();

    $this->actingAs($me)->get(route('tasks.index'))->assertNotFound();
    expect(fn () => saveTask($me))->toThrow(AuthorizationException::class);
});
