<?php

namespace App\Livewire\Projects;

use App\Domain\Projects\Actions\ChangeProjectStatus;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\LinkWork;
use App\Domain\Projects\Actions\ManageMilestones;
use App\Enums\Feature;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkflowRequest;
use App\Support\CurrentSchool;
use App\Support\Features;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One project: status pills, progress, milestones with deadlines, linked
 * To-Do tasks and workflow requests, team and details.
 */
class Show extends Component
{
    #[Locked]
    public int $projectId;

    public string $milestoneTitle = '';

    public string $milestoneDate = '';

    public string $milestoneTime = '';

    #[Locked]
    public ?int $editingMilestone = null;

    public string $taskSearch = '';

    public string $requestSearch = '';

    public ?int $linkMilestone = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->projectId = $project->id;

        // Opening the project clears its bell notifications.
        Auth::user()->unreadNotifications()->where('data->project_id', $project->id)->update(['read_at' => now()]);
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->with([
            'school', 'coordinator', 'creator', 'cover', 'partnerSchools', 'members.user', 'members.group', 'members.school',
            'milestones.completer',
        ])->findOrFail($this->projectId);
    }

    #[Computed]
    public function tasks()
    {
        return $this->project->tasks()->with(['milestone:id,title', 'school:id,short_name'])
            ->orderByRaw("case when tasks.status in ('todo','in_progress') then 0 else 1 end")
            ->orderByRaw('case when tasks.due_at is null then 1 else 0 end')->orderBy('tasks.due_at')->get();
    }

    #[Computed]
    public function requests()
    {
        return $this->project->workflowRequests()->with(['requester:id,first_name,last_name', 'school:id,short_name'])->latest('submitted_at')->get();
    }

    /** Tasks I may link: mine or assigned to me, shared, in a participating school, not linked yet. */
    #[Computed]
    public function taskResults()
    {
        if (mb_strlen(trim($this->taskSearch)) < 2) {
            return collect();
        }
        $user = Auth::user();

        return Task::query()->whereIn('tasks.school_id', $this->project->schoolIds())
            ->where('tasks.is_personal', false)->whereNull('tasks.project_id')
            ->where(fn ($q) => $q->where('tasks.created_by', $user->id)->orWhere(fn ($a) => $a->assignedTo($user)))
            ->where('tasks.title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->taskSearch)).'%')
            ->latest('tasks.id')->limit(6)->get(['id', 'title', 'status', 'due_at', 'all_day']);
    }

    #[Computed]
    public function requestResults()
    {
        if (mb_strlen(trim($this->requestSearch)) < 2) {
            return collect();
        }

        return WorkflowRequest::query()->visibleTo(Auth::user())
            ->whereIn('workflow_requests.school_id', $this->project->schoolIds())->whereNull('workflow_requests.project_id')
            ->where('workflow_requests.title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->requestSearch)).'%')
            ->latest('workflow_requests.id')->limit(6)->get(['id', 'title', 'status', 'template_name']);
    }

    private function refresh(): void
    {
        unset($this->project, $this->tasks, $this->requests, $this->taskResults, $this->requestResults);
    }

    public function setStatus(string $status, ChangeProjectStatus $change): void
    {
        $change->handle(Auth::user(), $this->project, ProjectStatus::from($status));
        $this->refresh();
        Flux::toast(text: __('Status: :status', ['status' => ProjectStatus::from($status)->label()]));
    }

    public function editMilestone(int $id): void
    {
        $m = $this->project->milestones->firstWhere('id', $id);
        abort_unless($m, 404);
        $this->editingMilestone = $m->id;
        $this->milestoneTitle = $m->title;
        $this->milestoneDate = $m->due_at?->format('Y-m-d') ?? '';
        $this->milestoneTime = $m->due_at && $m->due_at->format('H:i') !== '23:59' ? $m->due_at->format('H:i') : '';
    }

    public function cancelMilestone(): void
    {
        $this->reset('editingMilestone', 'milestoneTitle', 'milestoneDate', 'milestoneTime');
        $this->resetErrorBag();
    }

    public function saveMilestone(ManageMilestones $milestones): void
    {
        $this->validate([
            'milestoneTitle' => ['required', 'string', 'max:200'],
            'milestoneDate' => ['nullable', 'date_format:Y-m-d'],
            'milestoneTime' => ['nullable', 'date_format:H:i'],
        ], [], ['milestoneTitle' => __('nazwa'), 'milestoneDate' => __('termin')]);

        try {
            $milestones->save(Auth::user(), $this->project, [
                'title' => $this->milestoneTitle,
                'due_date' => $this->milestoneDate,
                'due_time' => $this->milestoneTime,
            ], $this->editingMilestone ? $this->project->milestones->firstWhere('id', $this->editingMilestone) : null);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->cancelMilestone();
        $this->refresh();
    }

    public function toggleMilestone(int $id, ManageMilestones $milestones): void
    {
        $m = $this->project->milestones->firstWhere('id', $id);
        abort_unless($m, 404);
        $milestones->toggle(Auth::user(), $m);
        $this->refresh();
    }

    public function moveMilestone(int $id, int $direction, ManageMilestones $milestones): void
    {
        $m = $this->project->milestones->firstWhere('id', $id);
        abort_unless($m, 404);
        $milestones->move(Auth::user(), $m, $direction);
        $this->refresh();
    }

    public function deleteMilestone(int $id, ManageMilestones $milestones): void
    {
        $m = $this->project->milestones->firstWhere('id', $id);
        abort_unless($m, 404);
        $milestones->delete(Auth::user(), $m);
        $this->refresh();
    }

    public function linkTask(int $id, LinkWork $link): void
    {
        $task = Task::query()->findOrFail($id);
        try {
            $link->task(Auth::user(), $this->project, $task, $this->linkMilestone);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->taskSearch = '';
        $this->refresh();
    }

    public function unlinkTask(int $id, LinkWork $link): void
    {
        $link->unlinkTask(Auth::user(), $this->project, Task::query()->findOrFail($id));
        $this->refresh();
    }

    public function linkRequest(int $id, LinkWork $link): void
    {
        $request = WorkflowRequest::query()->findOrFail($id);
        try {
            $link->request(Auth::user(), $this->project, $request);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->requestSearch = '';
        $this->refresh();
    }

    public function unlinkRequest(int $id, LinkWork $link): void
    {
        $link->unlinkRequest(Auth::user(), $this->project, WorkflowRequest::query()->findOrFail($id));
        $this->refresh();
    }

    public function delete(DeleteProject $delete)
    {
        $delete->handle(Auth::user(), $this->project);
        Flux::toast(text: __('Usunięto projekt.'));

        return $this->redirectRoute('projects.index', navigate: true);
    }

    public function render()
    {
        $project = $this->project;
        $user = Auth::user();
        $features = app(Features::class);

        return view('livewire.projects.show', [
            'statuses' => ProjectStatus::cases(),
            'canEdit' => $user->can('update', $project),
            'canWork' => $user->can('work', $project),
            'canDelete' => $user->can('delete', $project),
            'progress' => $project->progress(),
            'tasksOn' => $features->enabled(Feature::Tasks),
            'workflowsOn' => $features->enabled(Feature::Workflows),
            'inOwnerSchool' => $project->school_id === app(CurrentSchool::class)->id(),
        ])->title($project->name);
    }
}
