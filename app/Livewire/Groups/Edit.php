<?php

namespace App\Livewire\Groups;

use App\Enums\GroupType;
use App\Enums\MembershipMode;
use App\Models\Group;
use App\Support\Audit;
use App\Support\CurrentSchool;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    public ?Group $group = null;

    public string $name = '';

    public string $type = 'custom';

    public string $description = '';

    public string $color = '';

    public string $membership_mode = 'managed';

    public ?int $parent_id = null;

    public function mount(?Group $group = null): void
    {
        if ($group?->exists) {
            $this->authorize('update', $group);
            $this->group = $group;
            $this->name = $group->name;
            $this->type = $group->type->value;
            $this->description = (string) $group->description;
            $this->color = (string) $group->color;
            $this->membership_mode = $group->membership_mode->value;
            $this->parent_id = $group->parent_id;
        } else {
            $this->authorize('create', Group::class);
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(GroupType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'membership_mode' => ['required', Rule::enum(MembershipMode::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->whereNull('deleted_at')->where('school_id', app(CurrentSchool::class)->id()), Rule::notIn([$this->group?->id])],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => __('nazwa'), 'color' => __('kolor')];
    }

    public function save()
    {
        $this->authorize($this->group ? 'update' : 'create', $this->group ?? Group::class);
        $data = $this->validate();
        $data['color'] = $data['color'] ?: null;
        $data['description'] = $data['description'] ?: null;

        if ($this->group) {
            // Leaders may edit the description/colour of their group, but not
            // its type, hierarchy or how people join it.
            if (! Auth::user()->can('create', Group::class)) {
                $data = array_intersect_key($data, array_flip(['name', 'description', 'color']));
            }
            $this->group->update($data);
            Audit::log('group.updated', $this->group, ['changed' => array_keys($this->group->getChanges())]);
            $group = $this->group;
        } else {
            $group = Group::query()->create($data + ['created_by' => Auth::id()]);
            Audit::log('group.created', $group, ['name' => $group->name]);
        }

        Flux::toast(variant: 'success', text: __('Zapisano grupę.'));

        return $this->redirectRoute('groups.show', $group, navigate: true);
    }

    public function delete()
    {
        $this->authorize('delete', $this->group);

        Audit::log('group.deleted', $this->group, ['name' => $this->group->name]);
        $this->group->members()->detach();
        $this->group->delete();

        Flux::toast(text: __('Usunięto grupę.'));

        return $this->redirectRoute('groups.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.groups.edit', [
            'types' => GroupType::cases(),
            'modes' => MembershipMode::cases(),
            'parents' => Group::query()->inCurrentSchool()->when($this->group, fn ($q) => $q->whereKeyNot($this->group->id))->orderBy('name')->get(['id', 'name']),
            'canChangeStructure' => Auth::user()->can('create', Group::class),
        ])->title($this->group ? $this->group->name : __('Nowa grupa'));
    }
}
