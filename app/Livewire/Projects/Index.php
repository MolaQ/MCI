<?php

namespace App\Livewire\Projects;

use App\Domain\Projects\ProjectGuard;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Project list with pill filters: Moje projekty, Koordynowane przeze mnie,
 * then status (W toku, Planowane, Zakończone…) and school.
 */
#[Title('Projekty')]
class Index extends Component
{
    public const VIEWS = ['mine', 'coordinated', 'all'];

    public const STATUSES = ['in_progress', 'planned', 'on_hold', 'completed', 'cancelled'];

    #[Url(as: 'widok', except: 'mine')]
    public string $view = 'mine';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'szkola', except: '')]
    public string $school = '';

    public function mount(): void
    {
        $this->authorizeView();
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'mine';
        }
        // Nothing of mine yet: start with everything I can see.
        if ($this->view === 'mine' && ! request()->has('widok') && $this->counts['mine'] === 0) {
            $this->view = 'all';
        }
    }

    private function authorizeView(): void
    {
        abort_unless(app(CurrentSchool::class)->id(), 403);
    }

    public function setFilter(string $filter, string $value): void
    {
        abort_unless(in_array($filter, ['view', 'status', 'school'], true), 422);
        if ($filter === 'view' && ! in_array($value, self::VIEWS, true)) {
            $value = 'mine';
        }
        if ($filter === 'status' && $value !== '' && ! in_array($value, self::STATUSES, true)) {
            $value = '';
        }
        $this->{$filter} = $value;
    }

    private function managedSchool(): ?int
    {
        return app(ProjectGuard::class)->manages(Auth::user()) ? app(CurrentSchool::class)->id() : null;
    }

    private function base(string $view): Builder
    {
        $user = Auth::user();
        $q = Project::query()->visibleTo($user, $this->managedSchool());

        return match ($view) {
            'mine' => $q->involving($user),
            'coordinated' => $q->where('projects.coordinator_id', $user->id),
            default => $q,
        };
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        $open = [ProjectStatus::Planned->value, ProjectStatus::InProgress->value, ProjectStatus::OnHold->value];

        return [
            'mine' => $this->base('mine')->whereIn('projects.status', $open)->count(),
            'coordinated' => $this->base('coordinated')->whereIn('projects.status', $open)->count(),
        ];
    }

    /** Schools that have projects this person can see (for the school pill). */
    #[Computed]
    public function schools()
    {
        $ids = $this->base('all')->toBase()->distinct()->pluck('projects.school_id');

        return School::query()->whereKey($ids)->orderBy('name')->get(['id', 'name', 'short_name', 'slug', 'color', 'logo_media_id']);
    }

    #[Computed]
    public function projects()
    {
        $school = $this->schools->firstWhere('slug', $this->school);

        return $this->base($this->view)
            ->when($this->status !== '', fn ($q) => $q->where('projects.status', $this->status))
            ->when($school, fn ($q) => $q->inSchools([$school->id]))
            ->with(['school:id,short_name,color,logo_media_id', 'coordinator:id,first_name,last_name', 'cover', 'milestones:id,project_id,completed_at,due_at,title'])
            ->withCount('partnerSchools')
            ->orderByRaw("case projects.status when 'in_progress' then 0 when 'planned' then 1 when 'on_hold' then 2 else 3 end")
            ->orderByRaw('case when projects.ends_on is null then 1 else 0 end')->orderBy('projects.ends_on')
            ->latest('projects.id')
            ->limit(60)->get();
    }

    public function render()
    {
        return view('livewire.projects.index', [
            'canCreate' => Auth::user()->can('create', Project::class),
            'statuses' => array_map(fn ($s) => ProjectStatus::from($s), self::STATUSES),
        ]);
    }
}
