<?php

namespace App\Livewire\Tasks;

use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Actions\SaveTask;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\CurrentSchool;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Zadania": my list (personal + assigned to me, my groups and my roles)
 * or what I assigned to others, with pill filters, one-click status and a
 * quick-add line for personal To-Do.
 */
#[Title('Zadania')]
class Index extends Component
{
    use WithPagination;

    public const VIEWS = ['mine', 'assigned_by'];

    public const WHEN = ['all', 'overdue', 'today', 'week'];

    #[Url(as: 'widok', except: 'mine')]
    public string $view = 'mine';

    #[Url(as: 'kiedy', except: 'all')]
    public string $when = 'all';

    #[Url(as: 'grupa', except: '')]
    public string $group = '';

    #[Url(as: 'priorytet', except: '')]
    public string $priority = '';

    #[Url(as: 'szkoly', except: false)]
    public bool $allSchools = false;

    #[Url(as: 'zakonczone', except: false)]
    public bool $showDone = false;

    public string $quickTitle = '';

    public string $quickDue = '';

    public function setFilter(string $filter, string $value): void
    {
        $allowed = match ($filter) {
            'view' => in_array($value, self::VIEWS, true),
            'when' => in_array($value, self::WHEN, true),
            'group' => $value === '' || $this->groups->contains('id', (int) $value),
            'priority' => $value === '' || TaskPriority::tryFrom((int) $value) !== null,
            default => false,
        };
        abort_unless($allowed, 422);
        $this->{$filter} = $value;
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['allSchools', 'showDone'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('when', 'group', 'priority', 'showDone');
        $this->resetPage();
    }

    #[Computed]
    public function groups()
    {
        return Auth::user()->schoolGroups()->orderBy('name')->get(['groups.id', 'groups.name', 'groups.color']);
    }

    #[Computed]
    public function multiSchool(): bool
    {
        return Auth::user()->schools()->wherePivot('status', 'active')->count() > 1;
    }

    private function base(): Builder
    {
        $user = Auth::user();

        return Task::query()
            ->when($this->view === 'assigned_by', fn ($q) => $q->assignedBy($user), fn ($q) => $q->assignedTo($user))
            ->when(! $this->allSchools, fn ($q) => $q->where('tasks.school_id', app(CurrentSchool::class)->id()));
    }

    private function filtered(): Builder
    {
        return $this->base()
            ->when($this->when === 'overdue', fn ($q) => $q->overdue())
            ->when($this->when === 'today', fn ($q) => $q->whereBetween('tasks.due_at', [now()->startOfDay(), now()->endOfDay()]))
            ->when($this->when === 'week', fn ($q) => $q->whereBetween('tasks.due_at', [now()->startOfDay(), now()->endOfWeek()]))
            ->when($this->group !== '', fn ($q) => $q->whereExists(fn ($e) => $e->from('task_assignees')
                ->whereColumn('task_assignees.task_id', 'tasks.id')->where('task_assignees.group_id', (int) $this->group)))
            ->when($this->priority !== '', fn ($q) => $q->where('tasks.priority', (int) $this->priority))
            ->when(! $this->showDone && $this->when !== 'overdue', fn ($q) => $q->open());
    }

    /** @return array{mine: int, assigned_by: int, overdue: int, today: int, week: int} */
    #[Computed]
    public function counts(): array
    {
        $user = Auth::user();
        $school = fn ($q) => $this->allSchools ? $q : $q->where('tasks.school_id', app(CurrentSchool::class)->id());
        $view = $this->base()->open();

        return [
            'mine' => $school(Task::query()->assignedTo($user))->open()->count(),
            'assigned_by' => $school(Task::query()->assignedBy($user))->open()->count(),
            'overdue' => (clone $view)->overdue()->count(),
            'today' => (clone $view)->whereBetween('tasks.due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'week' => (clone $view)->whereBetween('tasks.due_at', [now()->startOfDay(), now()->endOfWeek()])->count(),
        ];
    }

    #[Computed]
    public function tasks()
    {
        $open = implode("','", TaskStatus::openValues());

        return $this->filtered()
            ->with(['school', 'creator:id,first_name,last_name', 'assignees.user:id,first_name,last_name', 'assignees.group:id,name,color'])
            ->withCount(['checklist', 'checklist as checklist_done_count' => fn ($q) => $q->where('is_done', true), 'images'])
            ->orderByRaw("case when tasks.status in ('{$open}') then 0 else 1 end")
            ->orderByRaw('case when tasks.due_at is null then 1 else 0 end')
            ->orderBy('tasks.due_at')
            ->orderByDesc('tasks.priority')
            ->orderByDesc('tasks.id')
            ->paginate(20);
    }

    /** The round button: do zrobienia → w trakcie → gotowe → do zrobienia. */
    public function cycle(int $id, ChangeTaskStatus $change): void
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize('work', $task);
        $change->handle(Auth::user(), $task, $task->status->next());
        unset($this->tasks, $this->counts);
    }

    public function setStatus(int $id, string $status, ChangeTaskStatus $change): void
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize('work', $task);
        $change->handle(Auth::user(), $task, TaskStatus::from($status));
        unset($this->tasks, $this->counts);
    }

    /** Quick personal To-Do: a title and, optionally, today / tomorrow. */
    public function quickAdd(SaveTask $save): void
    {
        $this->validate(['quickTitle' => ['required', 'string', 'max:200'], 'quickDue' => ['in:,today,tomorrow']], [], ['quickTitle' => __('tytuł')]);

        try {
            $save->handle(Auth::user(), app(CurrentSchool::class)->required(), [
                'title' => $this->quickTitle,
                'due_date' => match ($this->quickDue) {
                    'today' => now()->toDateString(),
                    'tomorrow' => now()->addDay()->toDateString(),
                    default => null,
                },
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->reset('quickTitle', 'quickDue');
        unset($this->tasks, $this->counts);
        Flux::toast(variant: 'success', text: __('Dodano zadanie.'));
    }

    public function render()
    {
        return view('livewire.tasks.index', [
            'priorities' => array_reverse(TaskPriority::cases()),
            'statuses' => TaskStatus::cases(),
            'filtered' => $this->when !== 'all' || $this->group !== '' || $this->priority !== '' || $this->showDone,
        ]);
    }
}
