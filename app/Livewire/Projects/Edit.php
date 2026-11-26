<?php

namespace App\Livewire\Projects;

use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\ProjectGuard;
use App\Enums\Feature;
use App\Enums\ProjectKind;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentSchool;
use App\Support\Features;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * New / edit project: name, kind, status, dates, coordinator, team (people,
 * groups, roles), partner schools, visibility, budget notes, description
 * with @mentions and a cover from the image editor. Rules: SaveProject.
 */
#[Title('Projekt')]
class Edit extends Component
{
    use WithImageUploads;

    #[Locked]
    public ?int $projectId = null;

    public string $name = '';

    public string $kind = 'event';

    public string $status = 'planned';

    public string $visibility = 'school';

    public string $starts_on = '';

    public string $ends_on = '';

    public ?int $coordinator_id = null;

    public string $budget_notes = '';

    public string $description = '';

    /** @var list<string> user:1, group:2, role:teacher */
    public array $members = [];

    /** @var list<int> */
    public array $partners = [];

    public string $peopleSearch = '';

    public string $coordinatorSearch = '';

    public $cover = null;

    public bool $removeCover = false;

    public function mount(?Project $project = null): void
    {
        if ($project?->exists) {
            $this->authorize('update', $project);
            abort_unless($project->school_id === app(CurrentSchool::class)->id(), 404);
            $this->projectId = $project->id;
            $this->fill([
                'name' => $project->name,
                'kind' => $project->kind->value,
                'status' => $project->status->value,
                'visibility' => $project->visibility,
                'starts_on' => $project->starts_on?->format('Y-m-d') ?? '',
                'ends_on' => $project->ends_on?->format('Y-m-d') ?? '',
                'coordinator_id' => $project->coordinator_id,
                'budget_notes' => (string) $project->budget_notes,
                'description' => (string) $project->description,
                'members' => $project->members()->get()->map->key()->all(),
                'partners' => $project->partnerSchools()->pluck('schools.id')->all(),
            ]);
        } else {
            $this->authorize('create', Project::class);
            $this->coordinator_id = Auth::id();
        }
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectId ? Project::query()->with('cover')->find($this->projectId) : null;
    }

    /** @return list<int> */
    private function schoolIds(): array
    {
        return [app(CurrentSchool::class)->id(), ...array_map('intval', $this->partners)];
    }

    #[Computed]
    public function partnerOptions()
    {
        return app(ProjectGuard::class)->partnerOptions(app(CurrentSchool::class)->required());
    }

    #[Computed]
    public function groups()
    {
        return app(ProjectGuard::class)->groupOptions(Auth::user(), $this->schoolIds());
    }

    #[Computed]
    public function canAddRoles(): bool
    {
        return app(ProjectGuard::class)->manages(Auth::user());
    }

    private function searchPeople(string $term)
    {
        if (mb_strlen(trim($term)) < 2) {
            return collect();
        }

        return User::query()->active()
            ->whereHas('schools', fn ($q) => $q->whereIn('schools.id', $this->schoolIds())->where('school_user.status', 'active'))
            ->search($term)->orderBy('last_name')->limit(6)->get();
    }

    #[Computed]
    public function peopleResults()
    {
        return $this->searchPeople($this->peopleSearch);
    }

    #[Computed]
    public function coordinatorResults()
    {
        return $this->searchPeople($this->coordinatorSearch);
    }

    #[Computed]
    public function coordinator(): ?User
    {
        return $this->coordinator_id ? User::query()->find($this->coordinator_id) : null;
    }

    #[Computed]
    public function selectedPeople()
    {
        $ids = collect($this->members)->filter(fn ($k) => str_starts_with($k, 'user:'))->map(fn ($k) => (int) substr($k, 5));

        return User::query()->whereKey($ids)->orderBy('last_name')->get();
    }

    public function toggleMember(string $key): void
    {
        $this->members = in_array($key, $this->members, true)
            ? array_values(array_diff($this->members, [$key]))
            : [...$this->members, $key];
    }

    public function addPerson(int $id): void
    {
        if (! in_array("user:{$id}", $this->members, true)) {
            $this->members[] = "user:{$id}";
        }
        $this->peopleSearch = '';
    }

    public function setCoordinator(int $id): void
    {
        $this->coordinator_id = $id;
        $this->coordinatorSearch = '';
        unset($this->coordinator);
    }

    public function togglePartner(int $id): void
    {
        $this->partners = in_array($id, $this->partners, true)
            ? array_values(array_diff($this->partners, [$id]))
            : [...$this->partners, $id];
        unset($this->groups);
    }

    public function updatedCover(): void
    {
        $this->validateOnly('cover', ['cover' => $this->imageUploadRule()]);
        $this->removeCover = false;
    }

    public function dropCover(): void
    {
        $this->cover = null;
        $this->removeCover = true;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', 'in:'.implode(',', array_column(ProjectKind::cases(), 'value'))],
            'status' => ['required', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'visibility' => ['required', 'in:school,team,public'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
            'coordinator_id' => ['required', 'integer'],
            'budget_notes' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:100000'],
            'members' => ['array', 'max:'.SaveProject::MAX_MEMBERS],
            'members.*' => ['string', 'max:40'],
            'partners' => ['array', 'max:20'],
            'partners.*' => ['integer'],
            'cover' => $this->imageUploadRule(),
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => __('nazwa'), 'starts_on' => __('początek'), 'ends_on' => __('koniec'), 'coordinator_id' => __('koordynator')];
    }

    public function save(SaveProject $save)
    {
        $this->validate();

        try {
            $project = $save->handle(Auth::user(), app(CurrentSchool::class)->required(), [
                'name' => $this->name,
                'kind' => $this->kind,
                'status' => $this->status,
                'visibility' => $this->visibility,
                'starts_on' => $this->starts_on,
                'ends_on' => $this->ends_on,
                'coordinator_id' => $this->coordinator_id,
                'budget_notes' => $this->budget_notes,
                'description' => $this->description,
            ], $this->members, $this->partners, $this->cover, $this->removeCover, $this->project);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }

        Flux::toast(variant: 'success', text: $this->projectId ? __('Zapisano projekt.') : __('Utworzono projekt.'));

        return $this->redirectRoute('projects.show', $project, navigate: true);
    }

    public function render()
    {
        $school = app(CurrentSchool::class)->required();

        return view('livewire.projects.edit', [
            'kinds' => ProjectKind::cases(),
            'statuses' => ProjectStatus::cases(),
            'roles' => array_filter(Role::cases(), fn (Role $r) => $r !== Role::Admin),
            'covers' => app(Features::class)->enabled(Feature::ProjectCovers, $school),
        ])->title($this->projectId ? __('Edycja projektu') : __('Nowy projekt'));
    }
}
