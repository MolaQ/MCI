<?php

use App\Domain\Workflows\Actions\CancelRequest;
use App\Domain\Workflows\Actions\DecideRequest;
use App\Domain\Workflows\Actions\ManageTemplates;
use App\Domain\Workflows\Actions\ResubmitRequest;
use App\Domain\Workflows\Actions\SubmitRequest;
use App\Enums\Feature;
use App\Enums\GroupRole;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Livewire\Workflows\Create;
use App\Livewire\Workflows\Index;
use App\Livewire\Workflows\Show;
use App\Livewire\Workflows\TemplateEdit;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\School;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowTemplate;
use App\Notifications\TaskNotification;
use App\Notifications\WorkflowNotification;
use App\Support\Features;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function tripTemplate(User $manager, array $stages, array $fields = [], ?School $school = null, array $data = []): WorkflowTemplate
{
    $school ??= test()->school;
    actAsSchool($school, $manager);

    return app(ManageTemplates::class)->save($manager, $school, $data + ['name' => 'Wycieczka '.uniqid()], $fields, $stages);
}

function submitTo(WorkflowTemplate $template, User $requester, array $data = [], string $title = 'Wyjazd 2b'): WorkflowRequest
{
    actAsSchool(test()->school, $requester);

    return app(SubmitRequest::class)->handle($requester, test()->school, $template, $title, $data);
}

function decide(User $who, WorkflowRequest $request, string $decision, ?string $comment = null): WorkflowRequest
{
    actAsSchool($request->approverSchool ?? test()->school, $who);

    return app(DecideRequest::class)->handle($who, $request->fresh(), $decision, $comment);
}

it('runs a two-stage request to approval with notifications, tasks and audit', function () {
    Notification::fake();
    $coordinator = userWithRole(Role::Coordinator);
    $director = userWithRole(Role::Teacher);
    $teacher = userWithRole(Role::Teacher);
    $template = tripTemplate($coordinator, [
        ['name' => 'Wicedyrektor', 'approver_type' => 'role', 'approver_id' => 'coordinator', 'sla_hours' => 48, 'task_on_reach' => true],
        ['name' => 'Dyrektor', 'approver_type' => 'user', 'approver_id' => $director->id, 'followup_task' => 'Dostarczyć listę uczestników'],
    ], [['label' => 'Liczba uczniów', 'type' => 'number', 'required' => true], ['label' => 'Transport', 'type' => 'select', 'options' => "Autokar\nPociąg", 'required' => true]]);

    expect(fn () => submitTo($template, $teacher, ['liczba_uczniow' => '', 'transport' => 'Rower']))->toThrow(ValidationException::class);
    $r = submitTo($template, $teacher, ['liczba_uczniow' => '24', 'transport' => 'Autokar']);

    expect($r->status)->toBe(WorkflowStatus::InProgress)
        ->and($r->stage_due_at->diffInHours(now()->addHours(48)))->toBeLessThan(1)
        ->and(WorkflowRequest::query()->awaitingDecisionBy($coordinator)->pluck('id')->all())->toBe([$r->id])
        ->and(WorkflowRequest::query()->awaitingDecisionBy($director)->count())->toBe(0)
        ->and($teacher->can('decide', $r))->toBeFalse()
        ->and(Task::query()->assignedTo($coordinator)->where('workflow_request_id', $r->id)->count())->toBe(1);
    Notification::assertSentTo($coordinator, WorkflowNotification::class, fn ($n) => $n->kind === 'pending');

    decide($coordinator, $r, 'approve');
    $r->refresh();
    expect($r->current_position)->toBe(1)
        ->and($r->approver_type)->toBe('user')
        ->and(Task::query()->where('workflow_request_id', $r->id)->where('is_personal', false)->first()->status)->toBe(TaskStatus::Done)
        ->and(WorkflowRequest::query()->awaitingDecisionBy($director)->count())->toBe(1);
    Notification::assertSentTo($director, WorkflowNotification::class, fn ($n) => $n->kind === 'pending');

    decide($director, $r, 'approve', '<p>Zgoda.</p>');
    $r->refresh();
    expect($r->status)->toBe(WorkflowStatus::Approved)
        ->and($r->completed_at)->not->toBeNull()
        ->and(Task::query()->assignedTo($teacher)->where('title', 'Dostarczyć listę uczestników')->exists())->toBeTrue()
        ->and($r->actions()->pluck('action')->all())->toBe(['submitted', 'approved', 'approved']);
    Notification::assertSentTo($teacher, WorkflowNotification::class, fn ($n) => $n->kind === 'approved');
    Notification::assertSentTo($teacher, TaskNotification::class);

    expect(AuditLog::query()->where('action', 'like', 'workflow.%')->pluck('action')->all())
        ->toContain('workflow.template_created', 'workflow.submitted', 'workflow.approved', 'workflow.advanced');
});

it('requires a comment to return or reject, and lets the requester fix and resubmit', function () {
    Notification::fake();
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    $template = tripTemplate($coordinator, [['name' => 'Akceptacja', 'approver_type' => 'role', 'approver_id' => 'coordinator']],
        [['label' => 'Cel', 'type' => 'text', 'required' => true]]);
    $r = submitTo($template, $teacher, ['cel' => 'Kraków']);

    expect(fn () => decide($coordinator, $r, 'return', '<p> </p>'))->toThrow(ValidationException::class);
    decide($coordinator, $r, 'return', '<p>Brakuje programu wycieczki.</p>');
    expect($r->fresh()->status)->toBe(WorkflowStatus::Returned)
        ->and($coordinator->can('decide', $r->fresh()))->toBeFalse();
    Notification::assertSentTo($teacher, WorkflowNotification::class, fn ($n) => $n->kind === 'returned');

    expect(fn () => app(ResubmitRequest::class)->handle($coordinator, $r->fresh(), 'X', ['cel' => 'Y']))->toThrow(AuthorizationException::class);
    actAsSchool($this->school, $teacher);
    app(ResubmitRequest::class)->handle($teacher, $r->fresh(), 'Wyjazd 2b (poprawiony)', ['cel' => 'Kraków i Wieliczka'], '<p>Dodałem program.</p>');
    expect($r->fresh()->status)->toBe(WorkflowStatus::InProgress)
        ->and($r->fresh()->data['cel'])->toBe('Kraków i Wieliczka');

    decide($coordinator, $r, 'reject', '<p>Termin koliduje z egzaminami.</p>');
    expect($r->fresh()->status)->toBe(WorkflowStatus::Rejected);
    Notification::assertSentTo($teacher, WorkflowNotification::class, fn ($n) => $n->kind === 'rejected');
    expect(fn () => app(CancelRequest::class)->handle($teacher, $r->fresh()))->toThrow(AuthorizationException::class);
});

it('routes to group members, keeps requests private and blocks self-approval', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $a = userWithRole(Role::Staff);
    $b = userWithRole(Role::Staff);
    $outsider = userWithRole(Role::Staff);
    $board = Group::factory()->create();
    $board->members()->attach($a->id, ['role' => GroupRole::Member->value]);
    $board->members()->attach($b->id, ['role' => GroupRole::Member->value]);
    $template = tripTemplate($coordinator, [['name' => 'Komisja', 'approver_type' => 'group', 'approver_id' => $board->id]]);

    $r = submitTo($template, $a);
    expect($a->can('decide', $r))->toBeFalse()
        ->and($b->can('decide', $r))->toBeTrue()
        ->and($outsider->can('view', $r))->toBeFalse()
        ->and($coordinator->can('view', $r))->toBeTrue(); // workflows.manage oversight

    $this->actingAs($outsider)->get(route('workflows.show', $r))->assertForbidden();
    $this->actingAs($b)->get(route('workflows.show', $r))->assertOk()->assertSee('Zatwierdź');

    // A stage nobody but the requester could decide is refused up front.
    $solo = Group::factory()->create();
    $solo->members()->attach($a->id, ['role' => GroupRole::Member->value]);
    $lonely = tripTemplate($coordinator, [['name' => 'Sam', 'approver_type' => 'group', 'approver_id' => $solo->id]]);
    expect(fn () => submitTo($lonely, $a))->toThrow(ValidationException::class);
});

it('validates templates and lets only authorised people manage them', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    $other = School::factory()->create();
    $foreignGroup = Group::factory()->create(['school_id' => $other->id]);

    expect(fn () => tripTemplate($teacher, [['name' => 'X', 'approver_type' => 'role', 'approver_id' => 'teacher']]))->toThrow(AuthorizationException::class)
        ->and(fn () => tripTemplate($coordinator, []))->toThrow(ValidationException::class)
        ->and(fn () => tripTemplate($coordinator, [['name' => 'X', 'approver_type' => 'group', 'approver_id' => $foreignGroup->id]]))->toThrow(ValidationException::class)
        ->and(fn () => tripTemplate($coordinator, [['name' => 'X', 'approver_type' => 'role', 'approver_id' => 'teacher', 'school_id' => $other->id]], data: []))->not->toThrow(Exception::class);

    // Cross-school stages need the feature.
    app(Features::class)->setForSchool($this->school, Feature::WorkflowCrossSchool, false);
    app()->forgetScopedInstances();
    expect(fn () => tripTemplate($coordinator, [['name' => 'X', 'approver_type' => 'role', 'approver_id' => 'teacher', 'school_id' => $other->id]]))->toThrow(ValidationException::class);

    // System-wide templates: site admins only; logged in the site journal.
    expect(fn () => app(ManageTemplates::class)->save($coordinator, null, ['name' => 'Wspólny'], [], [['name' => 'A', 'approver_type' => 'role', 'approver_id' => 'coordinator']]))->toThrow(AuthorizationException::class);
    $site = siteAdmin();
    $system = app(ManageTemplates::class)->save($site, null, ['name' => 'Wspólny'], [], [['name' => 'A', 'approver_type' => 'role', 'approver_id' => 'coordinator']]);
    expect(AuditLog::query()->where('action', 'workflow.template_created')->whereNull('school_id')->where('subject_id', $system->id)->exists())->toBeTrue()
        ->and(WorkflowTemplate::query()->availableIn($this->school)->whereKey($system->id)->exists())->toBeTrue();

    app(Features::class)->setForSchool($this->school, Feature::WorkflowSystemTemplates, false);
    app()->forgetScopedInstances();
    expect(WorkflowTemplate::query()->availableIn($this->school->fresh())->whereKey($system->id)->exists())->toBeFalse();
});

it('sends a cross-school stage to the other school and logs it there too', function () {
    Notification::fake();
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    $partner = School::factory()->create();
    actAsSchool($partner);
    $partnerHead = userWithRole(Role::Coordinator, school: $partner);
    actAsSchool($this->school);
    $template = tripTemplate($coordinator, [['name' => 'Szkoła partnerska', 'approver_type' => 'role', 'approver_id' => 'coordinator', 'school_id' => $partner->id]]);

    $r = submitTo($template, $teacher);
    expect($r->approver_school_id)->toBe($partner->id)
        ->and(WorkflowRequest::query()->awaitingDecisionBy($partnerHead)->count())->toBe(1)
        ->and(WorkflowRequest::query()->awaitingDecisionBy($coordinator)->count())->toBe(0);
    Notification::assertSentTo($partnerHead, WorkflowNotification::class);

    decide($partnerHead, $r, 'approve');
    expect(AuditLog::query()->where('action', 'workflow.approved')->pluck('school_id')->sort()->values()->all())
        ->toBe(collect([$this->school->id, $partner->id])->sort()->values()->all());
});

it('reminds approvers once a day after the deadline', function () {
    Notification::fake();
    $this->travelTo(now()->setTime(9, 0));
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    $template = tripTemplate($coordinator, [['name' => 'Szybko', 'approver_type' => 'role', 'approver_id' => 'coordinator', 'sla_hours' => 2]]);
    submitTo($template, $teacher);
    Notification::fake();

    $this->travel(3)->hours();
    $this->artisan('mci:workflow-reminders')->assertSuccessful();
    $this->artisan('mci:workflow-reminders');
    Notification::assertSentToTimes($coordinator, WorkflowNotification::class, 1);

    $this->travel(1)->day();
    $this->artisan('mci:workflow-reminders');
    Notification::assertSentToTimes($coordinator, WorkflowNotification::class, 2);
});

it('works end to end through the screens', function () {
    $coordinator = userWithRole(Role::Coordinator);
    $teacher = userWithRole(Role::Teacher);
    actAsSchool($this->school, $coordinator);
    $this->actingAs($coordinator);

    Livewire::test(TemplateEdit::class)
        ->set('name', 'Zakup sprzętu')
        ->set('stages.0.name', 'Kierownik')
        ->set('stages.0.approver_type', 'role')->set('stages.0.approver_id', 'coordinator')
        ->call('addField')->set('fields.0.label', 'Kwota')->set('fields.0.type', 'number')->set('fields.0.required', true)
        ->call('save')->assertHasNoErrors()->assertRedirect(route('admin.workflows'));
    $template = WorkflowTemplate::query()->where('name', 'Zakup sprzętu')->firstOrFail();
    expect($template->fields[0]['key'])->toBe('kwota');

    actAsSchool($this->school, $teacher);
    $this->actingAs($teacher);
    Livewire::test(Create::class)->assertSee('Zakup sprzętu')
        ->call('choose', $template->id)
        ->set('title', 'Projektor do sali 12')
        ->call('submit')->assertHasErrors('data.kwota')
        ->set('data.kwota', '2499,99')
        ->call('submit')->assertHasNoErrors()->assertRedirect();
    $r = WorkflowRequest::query()->where('title', 'Projektor do sali 12')->firstOrFail();
    expect($r->data['kwota'])->toBe('2499.99');

    actAsSchool($this->school, $coordinator);
    $this->actingAs($coordinator);
    Livewire::test(Index::class)->assertSet('view', 'decide')->assertSee('Projektor do sali 12')
        ->call('setFilter', 'view', 'mine')->assertDontSee('Projektor do sali 12');
    Livewire::test(Show::class, ['request' => $r])
        ->call('decide', 'reject')->assertHasErrors('comment')
        ->set('comment', '<p>Kupimy w przyszłym roku.</p>')->call('decide', 'reject')->assertHasNoErrors();
    expect($r->fresh()->status)->toBe(WorkflowStatus::Rejected);
});

it('hides the module when workflows are switched off', function () {
    $teacher = userWithRole(Role::Teacher);
    app(Features::class)->setForSchool($this->school, Feature::Workflows, false);
    app()->forgetScopedInstances();

    $this->actingAs($teacher)->get(route('workflows.index'))->assertNotFound();
});
