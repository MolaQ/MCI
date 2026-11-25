<?php

namespace App\Livewire\Events;

use App\Enums\ContentVisibility;
use App\Enums\TaxonomyKind;
use App\Models\Event;
use App\Support\CurrentSchool;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Events I may see: my school's, inter-school ones from partner schools and
 * public ones. The full multi-school calendar arrives with the Project
 * Manager step; this list already uses the same visibility rules.
 */
#[Title('Wydarzenia')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'zakres', except: 'school')]
    public string $scope = 'school';

    #[Url(as: 'kategoria', except: '')]
    public string $category = '';

    #[Url(as: 'kiedy', except: 'upcoming')]
    public string $when = 'upcoming';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function events()
    {
        $schoolId = app(CurrentSchool::class)->id();

        return Event::query()->visibleTo(Auth::user())
            ->with('school')
            ->when($this->scope === 'school', fn ($q) => $q->where('school_id', $schoolId))
            ->when($this->scope === 'partners', fn ($q) => $q->where('school_id', '!=', $schoolId)->where('visibility', '!=', ContentVisibility::SchoolOnly->value))
            ->when($this->scope === 'public', fn ($q) => $q->where('visibility', ContentVisibility::Public->value))
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->when($this->when === 'upcoming', fn ($q) => $q->upcoming()->orderBy('starts_at'))
            ->when($this->when === 'week', fn ($q) => $q->whereBetween('starts_at', [now()->startOfDay(), now()->endOfWeek()])->orderBy('starts_at'))
            ->when($this->when === 'month', fn ($q) => $q->whereBetween('starts_at', [now()->startOfDay(), now()->endOfMonth()])->orderBy('starts_at'))
            ->when($this->when === 'past', fn ($q) => $q->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at'))
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.events.index', [
            'categories' => app(Taxonomy::class)->options(TaxonomyKind::EventCategory, app(CurrentSchool::class)->get()),
            'canCreate' => Auth::user()->can('create', Event::class),
        ]);
    }
}
