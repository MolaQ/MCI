<?php

namespace App\Livewire\Taxonomy;

use App\Domain\Taxonomy\Actions\DeleteTerm;
use App\Domain\Taxonomy\Actions\HideTermInSchool;
use App\Domain\Taxonomy\Actions\MoveTerm;
use App\Domain\Taxonomy\Actions\SaveTerm;
use App\Domain\Taxonomy\Actions\SetTermActive;
use App\Domain\Taxonomy\TermCatalog;
use App\Enums\Permission;
use App\Enums\TermKind;
use App\Models\Event;
use App\Models\Group;
use App\Models\School;
use App\Models\Term;
use App\Support\CurrentSchool;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Event categories and group types, two scopes over one table:
 *  - scope "site":   Witryna → Kategorie i typy (portal-wide entries, site admins)
 *  - scope "school": Szkoła → Kategorie i typy (own entries + hiding portal-wide ones)
 */
class Manage extends Component
{
    #[Locked]
    public string $scope = 'school';

    #[Url(as: 'lista', except: 'event_category')]
    public string $kind = 'event_category';

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $icon = 'calendar';

    public string $color = '';

    public bool $is_featured = false;

    public function mount(string $scope = 'school'): void
    {
        $this->scope = $scope === 'site' ? 'site' : 'school';
        $this->kind = $this->termKind()->value;
        $this->authorizeScope();
    }

    private function authorizeScope(): void
    {
        $this->scope === 'site'
            ? $this->authorize('site-admin')
            : abort_unless(app(CurrentSchool::class)->id() && Auth::user()->can('manage', [Term::class, $this->school()]), 403);
    }

    private function school(): ?School
    {
        return $this->scope === 'school' ? app(CurrentSchool::class)->get()?->fresh() : null;
    }

    private function termKind(): TermKind
    {
        return TermKind::tryFrom($this->kind) ?? TermKind::EventCategory;
    }

    /** A term this screen may touch: portal-wide, or the active school's own. */
    private function findTerm(int $id): Term
    {
        return Term::query()->ofKind($this->termKind())
            ->where(fn ($q) => $q->whereNull('school_id')->when($this->scope === 'school', fn ($q) => $q->orWhere('school_id', app(CurrentSchool::class)->id())))
            ->findOrFail($id);
    }

    public function setKind(string $kind): void
    {
        $this->kind = (TermKind::tryFrom($kind) ?? TermKind::EventCategory)->value;
        unset($this->terms, $this->usage);
    }

    public function startCreate(): void
    {
        $this->authorize('create', [Term::class, $this->termKind(), $this->school()]);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->name = '';
        $this->icon = $this->termKind()->defaultIcon();
        $this->color = '';
        $this->is_featured = false;
        Flux::modal('term-form')->show();
    }

    public function startEdit(int $id): void
    {
        $term = $this->findTerm($id);
        $this->authorize('update', $term);
        $this->resetErrorBag();
        $this->editingId = $term->id;
        $this->name = $term->name;
        $this->icon = $term->icon();
        $this->color = (string) $term->color;
        $this->is_featured = $term->is_featured;
        Flux::modal('term-form')->show();
    }

    public function pickIcon(string $icon): void
    {
        if (in_array($icon, TermKind::ICONS, true)) {
            $this->icon = $icon;
        }
    }

    public function save(SaveTerm $save): void
    {
        $term = $this->editingId ? $this->findTerm($this->editingId) : null;
        $saved = $save->handle(Auth::user(), $this->termKind(), $this->school(), [
            'name' => $this->name, 'icon' => $this->icon, 'color' => $this->color, 'is_featured' => $this->is_featured,
        ], $term);

        Flux::modal('term-form')->close();
        Flux::toast(variant: 'success', text: $term ? __('Zapisano: :name', ['name' => $saved->name]) : __('Dodano: :name', ['name' => $saved->name]));
        unset($this->terms, $this->usage);
    }

    public function move(int $id, string $direction, MoveTerm $move): void
    {
        $move->handle(Auth::user(), $this->findTerm($id), $direction === 'up' ? 'up' : 'down');
        unset($this->terms);
    }

    public function toggleActive(int $id, SetTermActive $action): void
    {
        $term = $this->findTerm($id);
        $action->handle(Auth::user(), $term, ! $term->is_active);
        Flux::toast(text: $term->is_active ? __('Przywrócono: :name', ['name' => $term->name]) : __('Zarchiwizowano: :name', ['name' => $term->name]));
        unset($this->terms);
    }

    public function toggleHidden(int $id, HideTermInSchool $action): void
    {
        $school = $this->school();
        abort_unless($school, 403);
        $term = $this->findTerm($id);
        $hidden = ! in_array($term->id, app(TermCatalog::class)->hiddenIds($term->kind, $school), true);
        $action->handle(Auth::user(), $term, $school, $hidden);
        Flux::toast(text: $hidden ? __('Ukryto w szkole: :name', ['name' => $term->name]) : __('Znowu widoczne w szkole: :name', ['name' => $term->name]));
        unset($this->terms);
    }

    public function delete(int $id, DeleteTerm $delete): void
    {
        $term = $this->findTerm($id);
        $delete->handle(Auth::user(), $term);
        Flux::toast(text: __('Usunięto: :name', ['name' => $term->name]));
        unset($this->terms, $this->usage);
    }

    /**
     * @return Collection<int, Term>
     */
    #[Computed]
    public function terms()
    {
        return app(TermCatalog::class)->forManagement($this->termKind(), $this->school());
    }

    /**
     * Usage per term: everywhere on the site screen, in this school on the school screen.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function usage(): array
    {
        $schoolId = $this->scope === 'school' ? app(CurrentSchool::class)->id() : null;
        [$query, $column] = $this->termKind() === TermKind::EventCategory
            ? [Event::withTrashed()->withoutEagerLoads(), 'category_id']
            : [Group::withTrashed()->withoutEagerLoads(), 'type_id'];

        return $query->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw("{$column} as term_id, count(*) as aggregate")->groupBy($column)
            ->pluck('aggregate', 'term_id')->map(fn ($v) => (int) $v)->all();
    }

    public function render()
    {
        $this->authorizeScope();
        $school = $this->school();
        $kind = $this->termKind();
        $catalog = app(TermCatalog::class);

        return view('livewire.taxonomy.manage', [
            'kinds' => TermKind::cases(),
            'termKind' => $kind,
            'school' => $school,
            'customAllowed' => $school ? $catalog->customAllowed($kind, $school) : true,
            'canCreate' => Auth::user()->can('create', [Term::class, $kind, $school]),
            'canSwitchFeature' => $school && Auth::user()->can(Permission::SettingsManage->value),
            'hidden' => $school ? $catalog->hiddenIds($kind, $school) : [],
            'catalog' => $catalog,
            'editing' => $this->editingId ? Term::query()->find($this->editingId) : null,
        ])->title($this->scope === 'site' ? __('Kategorie i typy (witryna)') : __('Kategorie i typy'));
    }
}
