<?php

namespace App\Livewire\Workflows;

use App\Enums\Permission;
use App\Enums\WorkflowStatus;
use App\Models\WorkflowRequest;
use App\Models\WorkflowTemplate;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Obiegi: requests waiting for my decision, the ones I submitted, and
 * everything I may see, with status / template / school pill filters.
 */
#[Title('Obiegi i wnioski')]
class Index extends Component
{
    use WithPagination;

    public const VIEWS = ['decide', 'mine', 'all'];

    public const STATUSES = ['', 'open', 'done', 'rejected'];

    #[Url(as: 'widok', except: 'decide')]
    public string $view = 'decide';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'szablon', except: '')]
    public string $template = '';

    #[Url(as: 'szkoly', except: false)]
    public bool $allSchools = false;

    public function mount(): void
    {
        // Nothing waiting for me: open on my own requests instead of an empty list.
        if ($this->view === 'decide' && ! request()->has('widok') && $this->counts['decide'] === 0) {
            $this->view = 'mine';
        }
    }

    public function setFilter(string $filter, string $value): void
    {
        $allowed = match ($filter) {
            'view' => in_array($value, self::VIEWS, true),
            'status' => in_array($value, self::STATUSES, true),
            'template' => $value === '' || ctype_digit($value),
            default => false,
        };
        abort_unless($allowed, 422);
        $this->{$filter} = $value;
        if ($filter === 'view' && $value === 'decide') {
            $this->status = '';
        }
        $this->resetPage();
    }

    public function updatedAllSchools(): void
    {
        $this->resetPage();
    }

    private function schoolScope(Builder $q): Builder
    {
        return $this->allSchools ? $q : $q->where(fn ($w) => $w->where('workflow_requests.school_id', app(CurrentSchool::class)->id())
            ->orWhere('workflow_requests.approver_school_id', app(CurrentSchool::class)->id()));
    }

    private function base(string $view): Builder
    {
        $user = Auth::user();
        $manages = $user->can(Permission::WorkflowsManage->value);
        $q = WorkflowRequest::query();

        return $this->schoolScope(match ($view) {
            'decide' => $q->awaitingDecisionBy($user),
            'mine' => $q->where('workflow_requests.requester_id', $user->id),
            default => $q->where(fn ($w) => $w->visibleTo($user)
                ->when($manages, fn ($m) => $m->orWhere('workflow_requests.school_id', app(CurrentSchool::class)->id()))),
        });
    }

    /** @return array{decide: int, mine: int, returned: int} */
    #[Computed]
    public function counts(): array
    {
        return [
            'decide' => $this->base('decide')->count(),
            'mine' => $this->base('mine')->whereIn('workflow_requests.status', WorkflowStatus::openValues())->count(),
            'returned' => $this->base('mine')->where('workflow_requests.status', WorkflowStatus::Returned->value)->count(),
        ];
    }

    #[Computed]
    public function templates()
    {
        $school = app(CurrentSchool::class)->get();

        return $school ? WorkflowTemplate::query()->where(fn ($q) => $q->where('school_id', $school->id)->orWhereNull('school_id'))
            ->orderBy('name')->get(['id', 'name', 'icon']) : collect();
    }

    #[Computed]
    public function requests()
    {
        return $this->base($this->view)
            ->when($this->status === 'open', fn ($q) => $q->whereIn('workflow_requests.status', WorkflowStatus::openValues()))
            ->when($this->status === 'done', fn ($q) => $q->whereIn('workflow_requests.status', [WorkflowStatus::Approved->value, WorkflowStatus::Cancelled->value]))
            ->when($this->status === 'rejected', fn ($q) => $q->where('workflow_requests.status', WorkflowStatus::Rejected->value))
            ->when($this->template !== '', fn ($q) => $q->where('workflow_requests.template_id', (int) $this->template))
            ->with(['school', 'requester:id,first_name,last_name', 'template:id,icon'])
            ->orderByRaw("case when workflow_requests.status in ('in_progress','returned') then 0 else 1 end")
            ->orderByRaw('case when workflow_requests.stage_due_at is null then 1 else 0 end')
            ->orderBy('workflow_requests.stage_due_at')
            ->latest('workflow_requests.updated_at')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.workflows.index', [
            'multiSchool' => Auth::user()->schools()->wherePivot('status', 'active')->count() > 1,
        ]);
    }
}
