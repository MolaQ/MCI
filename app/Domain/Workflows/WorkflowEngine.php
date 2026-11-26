<?php

namespace App\Domain\Workflows;

use App\Enums\Feature;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Jobs\NotifyTask;
use App\Jobs\NotifyWorkflow;
use App\Models\Group;
use App\Models\School;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Support\Audit;
use App\Support\Features;
use Illuminate\Validation\ValidationException;

/**
 * Moves a request between stages: sets who decides now and until when
 * (SLA, Europe/Warsaw), notifies them, and creates or closes the To-Do
 * tasks a stage asks for. Shared by submit, decide and resubmit.
 */
class WorkflowEngine
{
    public function __construct(private Features $features) {}

    /** The school whose people decide at a stage. */
    public function stageSchoolId(array $stage, int $requestSchoolId): int
    {
        if ($stage['approver_type'] === 'group') {
            return (int) (Group::query()->whereKey((int) $stage['approver_id'])->value('school_id') ?? $stage['school_id'] ?? $requestSchoolId);
        }

        return (int) ($stage['school_id'] ?? $requestSchoolId);
    }

    /**
     * Before submitting: every stage must reach someone other than the
     * requester, and cross-school stages need the feature.
     *
     * @param  list<array<string, mixed>>  $stages
     */
    public function assertRoutable(array $stages, School $school, User $requester): void
    {
        foreach ($stages as $i => $stage) {
            $schoolId = $this->stageSchoolId($stage, $school->id);
            if ($schoolId !== $school->id && $this->features->disabled(Feature::WorkflowCrossSchool, $school)) {
                throw ValidationException::withMessages(['template' => __('Etap „:stage” wymaga obiegów międzyszkolnych, które są wyłączone.', ['stage' => $stage['name']])]);
            }
            $probe = new WorkflowRequest(['requester_id' => $requester->id, 'approver_type' => $stage['approver_type'], 'approver_id' => $stage['approver_id'], 'approver_school_id' => $schoolId]);
            if ($probe->approverIds()->isEmpty()) {
                throw ValidationException::withMessages(['template' => __('Na etapie :n („:stage”) nie ma nikogo (poza Tobą), kto mógłby podjąć decyzję. Wnioskodawca nie zatwierdza własnego wniosku — poproś administratora o poprawienie szablonu.', ['n' => $i + 1, 'stage' => $stage['name']])]);
            }
        }
    }

    public function enter(WorkflowRequest $request, int $position, ?User $actor): void
    {
        $stage = $request->stages[$position];
        $request->forceFill([
            'status' => 'in_progress',
            'current_position' => $position,
            'approver_type' => $stage['approver_type'],
            'approver_id' => (string) $stage['approver_id'],
            'approver_school_id' => $this->stageSchoolId($stage, $request->school_id),
            'stage_due_at' => $stage['sla_hours'] ? now()->addHours((int) $stage['sla_hours']) : null,
            'reminded_at' => null,
        ])->save();
        $request->unsetRelation('approverSchool');

        $approvers = $request->approverIds();
        if ($approvers->isNotEmpty()) {
            NotifyWorkflow::dispatch($request->id, $approvers->all(), 'pending', $actor?->id)->afterCommit();
        }

        if (! empty($stage['task_on_reach']) && $this->tasksEnabled($request->approver_school_id)) {
            $task = Task::query()->create([
                'school_id' => $request->approver_school_id,
                'workflow_request_id' => $request->id,
                'created_by' => $request->requester_id,
                'title' => mb_substr(__('Decyzja: :title', ['title' => $request->title]), 0, 200),
                'description' => '<p>'.e(__('Wniosek „:template”, etap „:stage”.', ['template' => $request->template_name, 'stage' => $stage['name']])).' <a href="'.e(route('workflows.show', $request, absolute: false)).'">'.e(__('Otwórz wniosek')).'</a></p>',
                'is_personal' => false,
                'priority' => TaskPriority::High,
                'status' => TaskStatus::Todo,
                'due_at' => $request->stage_due_at,
            ]);
            $task->assignees()->create([
                'user_id' => $stage['approver_type'] === 'user' ? (int) $stage['approver_id'] : null,
                'group_id' => $stage['approver_type'] === 'group' ? (int) $stage['approver_id'] : null,
                'role' => $stage['approver_type'] === 'role' ? $stage['approver_id'] : null,
                'assigned_by' => $request->requester_id,
                'assigned_at' => now(),
            ]);
        }
    }

    /** Closes the approvers' tasks of the current stage. */
    public function closeStageTasks(WorkflowRequest $request, TaskStatus $status, ?User $actor): void
    {
        $request->tasks()->where('is_personal', false)->open()->get()->each(fn (Task $t) => $t->forceFill([
            'status' => $status,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
            'completed_by' => $status === TaskStatus::Done ? $actor?->id : null,
        ])->save());
    }

    /** A personal To-Do for the requester once a stage with a follow-up is approved. */
    public function followUp(WorkflowRequest $request, array $stage, User $actor): void
    {
        if (empty($stage['followup_task']) || ! $request->requester_id || ! $this->tasksEnabled($request->school_id)) {
            return;
        }

        $task = Task::query()->create([
            'school_id' => $request->school_id,
            'workflow_request_id' => $request->id,
            'created_by' => $request->requester_id,
            'title' => mb_substr($stage['followup_task'], 0, 200),
            'description' => '<p>'.e(__('Po zatwierdzeniu etapu „:stage” wniosku „:title”.', ['stage' => $stage['name'], 'title' => $request->title])).' <a href="'.e(route('workflows.show', $request, absolute: false)).'">'.e(__('Otwórz wniosek')).'</a></p>',
            'is_personal' => true,
            'priority' => TaskPriority::Normal,
            'status' => TaskStatus::Todo,
        ]);
        NotifyTask::dispatch($task->id, [$request->requester_id], 'assigned', $actor->id)->afterCommit();
    }

    /** Logs to the requester's school and, for a cross-school step, to the approver's school too. */
    public function audit(WorkflowRequest $request, string $action, array $properties, User $actor): void
    {
        $properties = ['title' => $request->title, 'template' => $request->template_name] + $properties;
        Audit::log($action, $request, $properties, $actor, $request->school_id);
        if ($request->approver_school_id && $request->approver_school_id !== $request->school_id) {
            Audit::log($action, $request, $properties + ['cross_school' => true], $actor, $request->approver_school_id);
        }
    }

    private function tasksEnabled(int $schoolId): bool
    {
        return $this->features->enabled(Feature::WorkflowTasks, $schoolId) && $this->features->enabled(Feature::Tasks, $schoolId);
    }
}
