<?php

namespace App\Livewire\Workflows;

use App\Domain\Workflows\Actions\ManageTemplates;
use App\Enums\Feature;
use App\Enums\Role;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use App\Models\WorkflowTemplate;
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
 * Template editor: name, icon, description, the fields a request fills in,
 * how many attachments it needs, and the ordered stages (who decides, in
 * which school, SLA hours, To-Do tasks). Rules live in ManageTemplates.
 */
#[Title('Szablon obiegu')]
class TemplateEdit extends Component
{
    #[Locked]
    public string $scope = 'school';

    #[Locked]
    public ?int $templateId = null;

    public string $name = '';

    public string $description = '';

    public string $icon = 'clipboard-document-check';

    public int $min_attachments = 0;

    public bool $is_active = true;

    /** @var list<array{key: ?string, label: string, type: string, required: bool, options: string}> */
    public array $fields = [];

    /** @var list<array{name: string, approver_type: string, approver_id: string, school_id: string, sla_hours: string, task_on_reach: bool, followup_task: string}> */
    public array $stages = [];

    public function mount(?string $scope = 'school', ?WorkflowTemplate $template = null): void
    {
        $this->scope = $scope === 'site' ? 'site' : 'school';
        app(ManageTemplates::class)->authorize(Auth::user(), $this->school());

        if ($template?->exists) {
            abort_unless($template->school_id === $this->school()?->id, 404);
            $this->templateId = $template->id;
            $this->fill($template->only(['name', 'icon', 'min_attachments', 'is_active']));
            $this->description = (string) $template->description;
            $this->fields = collect($template->fields ?? [])->map(fn ($f) => [
                'key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'], 'required' => (bool) $f['required'],
                'options' => implode("\n", $f['options'] ?? []),
            ])->all();
            $this->stages = $template->stages()->get()->map(fn ($s) => [
                'name' => $s->name, 'approver_type' => $s->approver_type, 'approver_id' => (string) $s->approver_id,
                'school_id' => (string) ($s->school_id ?? ''), 'sla_hours' => (string) ($s->sla_hours ?? ''),
                'task_on_reach' => $s->task_on_reach, 'followup_task' => (string) $s->followup_task,
            ])->all();
        } else {
            $this->stages = [$this->blankStage()];
        }
    }

    private function school(): ?School
    {
        return $this->scope === 'site' ? null : app(CurrentSchool::class)->get();
    }

    private function blankStage(): array
    {
        return ['name' => '', 'approver_type' => 'role', 'approver_id' => Role::Coordinator->value, 'school_id' => '', 'sla_hours' => '', 'task_on_reach' => false, 'followup_task' => ''];
    }

    #[Computed]
    public function crossSchool(): bool
    {
        return app(Features::class)->enabled(Feature::WorkflowCrossSchool, $this->school());
    }

    #[Computed]
    public function schools()
    {
        return School::query()->where('is_active', true)->orderBy('short_name')->get(['id', 'short_name', 'name']);
    }

    #[Computed]
    public function tasksAvailable(): bool
    {
        return app(Features::class)->enabled(Feature::WorkflowTasks, $this->school());
    }

    /** Groups and people to choose from for a stage deciding in $schoolId. */
    public function optionsFor(?int $schoolId): array
    {
        static $cache = [];
        if (! $schoolId) {
            return ['groups' => collect(), 'users' => collect()];
        }

        return $cache[$schoolId] ??= [
            'groups' => Group::query()->where('school_id', $schoolId)->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->activeInSchool($schoolId)->active()->orderBy('last_name')->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name']),
        ];
    }

    public function updated(string $property): void
    {
        // Changing who decides (type or school) clears the chosen group/person.
        if (preg_match('/^stages\.(\d+)\.(approver_type|school_id)$/', $property, $m)) {
            $this->stages[(int) $m[1]]['approver_id'] = $this->stages[(int) $m[1]]['approver_type'] === 'role' ? Role::Coordinator->value : '';
        }
    }

    public function addField(): void
    {
        $this->fields[] = ['key' => null, 'label' => '', 'type' => 'text', 'required' => false, 'options' => ''];
    }

    public function addStage(): void
    {
        if (count($this->stages) < ManageTemplates::MAX_STAGES) {
            $this->stages[] = $this->blankStage();
        }
    }

    public function remove(string $list, int $index): void
    {
        abort_unless(in_array($list, ['fields', 'stages'], true), 422);
        unset($this->{$list}[$index]);
        $this->{$list} = array_values($this->{$list});
    }

    public function move(string $list, int $index, int $direction): void
    {
        abort_unless(in_array($list, ['fields', 'stages'], true), 422);
        $items = $this->{$list};
        $target = $index + $direction;
        if (isset($items[$index], $items[$target])) {
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
            $this->{$list} = $items;
        }
    }

    public function save(ManageTemplates $manage)
    {
        $template = $this->templateId ? WorkflowTemplate::query()->findOrFail($this->templateId) : null;

        try {
            $template = $manage->save(Auth::user(), $this->school(), [
                'name' => $this->name, 'description' => $this->description, 'icon' => $this->icon,
                'min_attachments' => $this->min_attachments, 'is_active' => $this->is_active,
            ], $this->fields, $this->stages, $template);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }

        Flux::toast(variant: 'success', text: __('Zapisano szablon „:name”.', ['name' => $template->name]));

        return $this->redirectRoute($this->scope === 'site' ? 'site.workflows' : 'admin.workflows', navigate: true);
    }

    public function render()
    {
        return view('livewire.workflows.template-edit', [
            'icons' => ManageTemplates::ICONS,
            'roles' => Role::cases(),
            'types' => [
                'text' => __('Krótki tekst'), 'textarea' => __('Dłuższy opis'), 'date' => __('Data'),
                'number' => __('Liczba / kwota'), 'select' => __('Lista wyboru'), 'checkbox' => __('Pole wyboru (tak/nie)'),
            ],
            'homeSchoolId' => $this->school()?->id,
        ])->title($this->templateId ? __('Edycja szablonu') : __('Nowy szablon obiegu'));
    }
}
