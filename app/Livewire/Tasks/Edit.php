<?php

namespace App\Livewire\Tasks;

use App\Domain\Projects\Actions\LinkWork;
use App\Domain\Tasks\Actions\SaveTask;
use App\Domain\Tasks\TaskGuard;
use App\Enums\Feature;
use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Livewire\Concerns\LinksToProject;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\Task;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * New / edit task: title, rich description with @mentions, priority,
 * due date and time (Europe/Warsaw), reminder, who it is for (me, people,
 * groups, roles), checklist and images. Rules live in SaveTask.
 */
#[Title('Zadanie')]
class Edit extends Component
{
    use LinksToProject, WithImageUploads;

    #[Locked]
    public ?int $taskId = null;

    public string $title = '';

    public string $description = '';

    public int $priority = 2;

    public string $due_date = '';

    public string $due_time = '';

    public string $remind = '';

    /** @var list<string> user:1, group:2, role:teacher */
    public array $assignees = [];

    public string $peopleSearch = '';

    /** @var list<array{id: ?int, body: string, is_done: bool}> */
    public array $checklist = [];

    public string $newItem = '';

    public $newImage = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $images = [];

    /** @var list<int> */
    public array $removeImageIds = [];

    public function mount(?Task $task = null): void
    {
        if ($task?->exists) {
            $this->authorize('update', $task);
            abort_unless($task->school_id === app(CurrentSchool::class)->id(), 404);
            $this->taskId = $task->id;
            $this->title = $task->title;
            $this->description = (string) $task->description;
            $this->priority = $task->priority->value;
            $this->due_date = $task->due_at?->format('Y-m-d') ?? '';
            $this->due_time = $task->due_at && ! $task->all_day ? $task->due_at->format('H:i') : '';
            $this->remind = $task->remind_before_minutes !== null ? (string) $task->remind_before_minutes : '';
            $this->assignees = $task->assignees()->get()->map->key()->all();
            $this->checklist = $task->checklist()->get()->map(fn ($i) => ['id' => $i->id, 'body' => $i->body, 'is_done' => $i->is_done])->all();
        } else {
            $this->authorize('create', Task::class);
            $this->rememberProject();
        }
    }

    #[Computed]
    public function task(): ?Task
    {
        return $this->taskId ? Task::query()->with('images')->find($this->taskId) : null;
    }

    #[Computed]
    public function canAssignOthers(): bool
    {
        return app(TaskGuard::class)->canAssignOthers(Auth::user(), app(CurrentSchool::class)->required());
    }

    #[Computed]
    public function groups()
    {
        return app(TaskGuard::class)->assignableGroups(Auth::user(), app(CurrentSchool::class)->required());
    }

    #[Computed]
    public function canAssignRoles(): bool
    {
        return app(TaskGuard::class)->canAssignRole(Auth::user(), app(CurrentSchool::class)->required());
    }

    #[Computed]
    public function peopleResults()
    {
        if (! $this->canAssignOthers || mb_strlen(trim($this->peopleSearch)) < 2) {
            return collect();
        }
        $user = Auth::user();
        $school = app(CurrentSchool::class)->required();
        $guard = app(TaskGuard::class);

        return User::query()->activeInSchool($school)->active()
            ->search($this->peopleSearch)
            ->whereKeyNot($user->id)
            ->unless($guard->assignsAny($user), fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $user->ledGroups()->where('groups.school_id', $school->id)->pluck('groups.id'))))
            ->orderBy('last_name')->limit(6)->get();
    }

    #[Computed]
    public function selectedPeople()
    {
        $ids = collect($this->assignees)->filter(fn ($k) => str_starts_with($k, 'user:'))->map(fn ($k) => (int) substr($k, 5));

        return User::query()->whereKey($ids)->orderBy('last_name')->get();
    }

    public function toggleAssignee(string $key): void
    {
        $this->assignees = in_array($key, $this->assignees, true)
            ? array_values(array_diff($this->assignees, [$key]))
            : [...$this->assignees, $key];
    }

    public function addPerson(int $id): void
    {
        if (! in_array("user:{$id}", $this->assignees, true)) {
            $this->assignees[] = "user:{$id}";
        }
        $this->peopleSearch = '';
    }

    public function clearDue(): void
    {
        $this->reset('due_date', 'due_time', 'remind');
    }

    public function onlyMe(): void
    {
        $this->assignees = [];
    }

    public function addItem(): void
    {
        $body = trim($this->newItem);
        if ($body === '') {
            return;
        }
        if (count($this->checklist) >= config('mci.tasks.max_checklist_items')) {
            $this->addError('checklist', __('Lista może mieć najwyżej :count punktów.', ['count' => config('mci.tasks.max_checklist_items')]));

            return;
        }
        $this->checklist[] = ['id' => null, 'body' => mb_substr($body, 0, 200), 'is_done' => false];
        $this->newItem = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->checklist[$index]);
        $this->checklist = array_values($this->checklist);
    }

    public function moveItem(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (isset($this->checklist[$index], $this->checklist[$target])) {
            [$this->checklist[$index], $this->checklist[$target]] = [$this->checklist[$target], $this->checklist[$index]];
        }
    }

    public function updatedNewImage(): void
    {
        $this->validateOnly('newImage', ['newImage' => $this->imageUploadRule()]);
        $kept = $this->task ? $this->task->images->whereNotIn('id', $this->removeImageIds)->count() : 0;

        if (app(Features::class)->disabled(Feature::TaskAttachments)) {
            $this->addError('newImage', __('Obrazy w zadaniach są wyłączone.'));
        } elseif ($kept + count($this->images) >= config('mci.media.max_post_images')) {
            $this->addError('newImage', __('Możesz dodać najwyżej :count obrazów.', ['count' => config('mci.media.max_post_images')]));
        } elseif ($this->newImage) {
            $this->images[] = $this->newImage;
        }
        $this->newImage = null;
    }

    public function removeNewImage(int $index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function removeStoredImage(int $id): void
    {
        $this->removeImageIds = array_values(array_unique([...$this->removeImageIds, $id]));
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:100000'],
            'priority' => ['required', 'integer', 'between:1,4'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'remind' => ['nullable', 'in:,0,15,60,180,1440,2880'],
            'assignees' => ['array', 'max:50'],
            'assignees.*' => ['string', 'max:40'],
            'checklist' => ['array'],
            'checklist.*.body' => ['nullable', 'string', 'max:200'],
            'images' => ['array', 'max:'.config('mci.media.max_post_images')],
            'images.*' => $this->imageUploadRule(),
        ];
    }

    protected function validationAttributes(): array
    {
        return ['title' => __('tytuł'), 'description' => __('opis'), 'due_date' => __('dzień terminu'), 'due_time' => __('godzina')];
    }

    public function save(SaveTask $save)
    {
        $this->validate();

        try {
            $task = $save->handle(Auth::user(), app(CurrentSchool::class)->required(), [
                'title' => $this->title,
                'description' => $this->description,
                'priority' => $this->priority,
                'due_date' => $this->due_date,
                'due_time' => $this->due_time,
                'remind_before_minutes' => $this->remind === '' ? null : (int) $this->remind,
            ], $this->assignees, $this->checklist, $this->images, $this->removeImageIds, $this->task);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }

        if ($this->linkProject && ! $task->is_personal) {
            app(LinkWork::class)->task(Auth::user(), $this->linkProject, $task);

            Flux::toast(variant: 'success', text: __('Utworzono zadanie w projekcie.'));

            return $this->redirectRoute('projects.show', $this->linkProject, navigate: true);
        }

        Flux::toast(variant: 'success', text: $this->taskId ? __('Zapisano zadanie.') : __('Utworzono zadanie.'));

        return $this->redirectRoute('tasks.show', $task, navigate: true);
    }

    public function render()
    {
        return view('livewire.tasks.edit', [
            'priorities' => TaskPriority::cases(),
            'roles' => array_filter(Role::cases(), fn (Role $r) => $r !== Role::Admin),
            'reminders' => app(Features::class)->enabled(Feature::TaskReminders),
            'attachments' => app(Features::class)->enabled(Feature::TaskAttachments),
        ])->title($this->taskId ? __('Edycja zadania') : __('Nowe zadanie'));
    }
}
