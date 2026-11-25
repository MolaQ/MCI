<?php

namespace App\Livewire\Catalog;

use App\Domain\Catalog\Actions\ManageTerms;
use App\Enums\Permission;
use App\Enums\TaxonomyKind;
use App\Models\School;
use App\Models\TaxonomyTerm;
use App\Support\CurrentSchool;
use App\Support\Taxonomy;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kategorie i typy: event categories and group types.
 *  - scope "site":   Witryna → Kategorie i typy, the list every school starts with (site admin);
 *  - scope "school": Administracja → Kategorie i typy, the school's own list and which
 *                    site-wide items it hides (categories.manage, when the site allows it).
 */
#[Title('Kategorie i typy')]
class Manage extends Component
{
    public string $scope = 'school';

    #[Url(as: 'lista', except: 'event_category')]
    public string $kind = 'event_category';

    public ?int $editingId = null;

    public string $name = '';

    public string $icon = 'tag';

    public string $color = '#64748b';

    public function mount(string $scope = 'school'): void
    {
        $this->scope = $scope === 'site' ? 'site' : 'school';
        $this->kind = TaxonomyKind::tryFrom($this->kind)?->value ?? 'event_category';
        $this->authorizeView();
    }

    private function school(): ?School
    {
        return $this->scope === 'site' ? null : app(CurrentSchool::class)->get();
    }

    private function kindEnum(): TaxonomyKind
    {
        return TaxonomyKind::from($this->kind);
    }

    private function authorizeView(): void
    {
        $user = Auth::user();
        abort_unless($this->scope === 'site'
            ? $user->can('site-admin')
            : app(CurrentSchool::class)->id() && $user->can(Permission::CategoriesManage->value), 403);
    }

    public function setKind(string $kind): void
    {
        $this->kind = TaxonomyKind::from($kind)->value;
        $this->cancel();
    }

    public function startCreate(): void
    {
        $this->editingId = 0;
        $this->name = '';
        $this->icon = $this->kindEnum()->defaultIcon();
        $this->color = '#64748b';
        $this->resetErrorBag();
        Flux::modal('term')->show();
    }

    public function edit(int $id): void
    {
        $term = $this->find($id);
        $this->editingId = $term->id;
        $this->name = $term->name;
        $this->icon = $term->icon;
        $this->color = $term->color ?: '#64748b';
        $this->resetErrorBag();
        Flux::modal('term')->show();
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function save(ManageTerms $terms): void
    {
        $this->authorizeView();
        $data = ['name' => $this->name, 'icon' => $this->icon, 'color' => $this->color];

        if ($this->editingId) {
            $terms->update(Auth::user(), $this->find($this->editingId), $this->school(), $data);
            Flux::toast(variant: 'success', text: __('Zapisano zmiany.'));
        } else {
            $terms->create(Auth::user(), $this->kindEnum(), $this->school(), $data);
            Flux::toast(variant: 'success', text: __('Dodano.'));
        }

        Flux::modal('term')->close();
        $this->cancel();
    }

    public function move(int $id, int $direction, ManageTerms $terms): void
    {
        $terms->move(Auth::user(), $this->find($id), $this->school(), $direction);
    }

    public function toggleActive(int $id, ManageTerms $terms): void
    {
        $term = $this->find($id);
        $terms->setActive(Auth::user(), $term, $this->school(), ! $term->is_active);
        Flux::toast(text: $term->is_active ? __('Przywrócono: :n', ['n' => $term->name]) : __('Zarchiwizowano: :n', ['n' => $term->name]));
    }

    public function toggleHidden(int $id, ManageTerms $terms): void
    {
        $term = $this->find($id);
        $school = $this->school();
        abort_unless($school, 404);
        $hidden = in_array($term->key, app(Taxonomy::class)->hiddenKeys($term->kind, $school), true);
        $terms->setHidden(Auth::user(), $term, $school, ! $hidden);
        Flux::toast(text: $hidden ? __('Pokazano w szkole: :n', ['n' => $term->name]) : __('Ukryto w szkole: :n', ['n' => $term->name]));
    }

    public function toggleFeatured(int $id, ManageTerms $terms): void
    {
        $term = $this->find($id);
        $terms->setFeatured(Auth::user(), $term, $this->school(), ! $term->is_featured);
        Flux::toast(text: $term->is_featured ? __('Wyróżniono na stronie głównej: :n', ['n' => $term->name]) : __('Bez wyróżnienia: :n', ['n' => $term->name]));
    }

    public function delete(int $id, ManageTerms $terms): void
    {
        $term = $this->find($id);
        $terms->delete(Auth::user(), $term, $this->school());
        Flux::toast(text: __('Usunięto: :n', ['n' => $term->name]));
    }

    private function find(int $id): TaxonomyTerm
    {
        $this->authorizeView();

        return TaxonomyTerm::query()->ofKind($this->kindEnum())->findOrFail($id);
    }

    public function render(ManageTerms $terms, Taxonomy $taxonomy)
    {
        $kind = $this->kindEnum();
        $school = $this->school();
        $all = TaxonomyTerm::query()->ofKind($kind)->orderBy('position')->orderBy('id')->get();
        $usage = fn (TaxonomyTerm $t) => $terms->usage($t, $school?->id);

        return view('livewire.catalog.manage', [
            'kinds' => TaxonomyKind::cases(),
            'kindEnum' => $kind,
            'site' => $all->whereNull('school_id')->values()->map(fn ($t) => ['term' => $t, 'usage' => $usage($t)]),
            'own' => $school ? $all->where('school_id', $school->id)->values()->map(fn ($t) => ['term' => $t, 'usage' => $usage($t)]) : collect(),
            'hidden' => $taxonomy->hiddenKeys($kind, $school),
            'mayCustomise' => $school === null || $taxonomy->schoolMayCustomise($kind, $school),
            'icons' => ManageTerms::ICONS,
            'schoolName' => $school?->short_name,
        ]);
    }
}
