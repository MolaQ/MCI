<?php

namespace App\Livewire\Groups;

use App\Domain\Media\Actions\ReplaceImage;
use App\Domain\Taxonomy\TermCatalog;
use App\Enums\Feature;
use App\Enums\MembershipMode;
use App\Enums\TermKind;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\Group;
use App\Support\Audit;
use App\Support\CurrentSchool;
use App\Support\Features;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Edit extends Component
{
    use WithImageUploads;

    public $logoUpload = null;

    public ?Group $group = null;

    public string $name = '';

    public ?int $type_id = null;

    /** Type the group had when the form opened (stays selectable even if archived). */
    #[Locked]
    public ?int $originalTypeId = null;

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
            $this->type_id = $this->originalTypeId = $group->type_id;
            $this->description = (string) $group->description;
            $this->color = (string) $group->color;
            $this->membership_mode = $group->membership_mode->value;
            $this->parent_id = $group->parent_id;
        } else {
            $this->authorize('create', Group::class);
            $this->type_id = app(TermCatalog::class)->fallback(TermKind::GroupType, app(CurrentSchool::class)->get())?->id;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type_id' => ['required', 'integer', Rule::in($this->typeIds())],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'membership_mode' => ['required', Rule::enum(MembershipMode::class)],
            'logoUpload' => $this->imageUploadRule(),
            'parent_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->whereNull('deleted_at')->where('school_id', app(CurrentSchool::class)->id()), Rule::notIn([$this->group?->id]), $this->noCycle(...)],
        ];
    }

    /**
     * The new parent may not be this group or any of its descendants.
     */
    private function noCycle(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! $this->group || ! $value) {
            return;
        }

        $seen = [];
        for ($id = (int) $value; $id && ! isset($seen[$id]); $id = (int) Group::query()->whereKey($id)->value('parent_id')) {
            if ($id === $this->group->id) {
                $fail(__('Grupa nadrzędna nie może być grupą podrzędną tej grupy.'));

                return;
            }
            $seen[$id] = true;
        }
    }

    protected function validationAttributes(): array
    {
        return ['name' => __('nazwa'), 'color' => __('kolor'), 'type_id' => __('typ')];
    }

    /**
     * Types this school may use, plus the group's current one.
     *
     * @return list<int>
     */
    private function typeIds(): array
    {
        $ids = app(TermCatalog::class)->allowedIds(TermKind::GroupType, app(CurrentSchool::class)->get());

        return $this->originalTypeId ? [...$ids, $this->originalTypeId] : $ids;
    }

    public function save()
    {
        $this->authorize($this->group ? 'update' : 'create', $this->group ?? Group::class);
        $data = $this->validate();
        unset($data['logoUpload']);
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

        if ($this->logoUpload && app(Features::class)->enabled(Feature::GroupLogos)) {
            app(ReplaceImage::class)->handle($group, 'logo_media_id', $this->logoUpload, 'logo', false, Auth::user(), 'logoUpload');
        }

        Flux::toast(variant: 'success', text: __('Zapisano grupę.'));

        return $this->redirectRoute('groups.show', $group, navigate: true);
    }

    public function updatedLogoUpload(): void
    {
        $this->validateOnly('logoUpload');
    }

    public function removeLogo(ReplaceImage $replace): void
    {
        $this->authorize('update', $this->group);
        $replace->handle($this->group, 'logo_media_id', null, 'logo', false, Auth::user(), 'logoUpload', remove: true);
        Flux::toast(text: __('Usunięto logo grupy.'));
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
        $canChangeStructure = Auth::user()->can('create', Group::class);

        return view('livewire.groups.edit', [
            'types' => app(TermCatalog::class)->forSchool(TermKind::GroupType, app(CurrentSchool::class)->get(), $this->originalTypeId),
            'modes' => MembershipMode::cases(),
            // Leaders cannot change the hierarchy, so they get no list of other groups.
            'parents' => $canChangeStructure
                ? Group::query()->inCurrentSchool()->when($this->group, fn ($q) => $q->whereKeyNot($this->group->id))->orderBy('name')->get(['id', 'name'])
                : Group::query()->whereKey($this->group?->parent_id)->get(['id', 'name']),
            'canChangeStructure' => $canChangeStructure,
        ])->title($this->group ? $this->group->name : __('Nowa grupa'));
    }
}
