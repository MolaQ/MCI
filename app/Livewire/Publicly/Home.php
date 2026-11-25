<?php

namespace App\Livewire\Publicly;

use App\Enums\Feature;
use App\Enums\TermKind;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\School;
use App\Models\Term;
use App\Support\Features;
use App\Support\Mentions;
use App\Support\RichText;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public information centre (no login): a live summary, one row of pill
 * filters, a "featured" bento (next highlighted events + pinned public
 * announcements), the event list and the school directory with a preview
 * drawer. Every query goes through a "publiclyVisible" / "publiclyListed"
 * scope, which selects public columns only.
 */
#[Layout('layouts::public')]
#[Title('Centrum informacji')]
class Home extends Component
{
    public const PAGE = 6;

    #[Url(as: 'szkola', except: '')]
    public string $school = '';

    #[Url(as: 'kategoria', except: '')]
    public string $category = '';

    #[Url(as: 'kiedy', except: 'upcoming')]
    public string $when = 'upcoming';

    public int $limit = self::PAGE;

    public string $code = '';

    public ?string $previewSlug = null;

    public function mount(): void
    {
        // Portal switched off for the whole site: send people where they can go.
        if (app(Features::class)->disabled(Feature::PublicFront, null)) {
            $this->redirectRoute(auth()->check() ? 'dashboard' : 'login');
        }
        if (! in_array($this->when, ['upcoming', 'today', 'week', 'month'], true)) {
            $this->when = 'upcoming';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['school', 'category', 'when'], true)) {
            $this->limit = self::PAGE;
        }
    }

    public function setFilter(string $filter, string $value): void
    {
        match ($filter) {
            'school' => $this->school = $this->school === $value ? '' : $value,
            'category' => $this->category = $this->category === $value ? '' : $value,
            'when' => $this->when = in_array($value, ['upcoming', 'today', 'week', 'month'], true) ? $value : 'upcoming',
            default => null,
        };
        $this->limit = self::PAGE;
    }

    public function clearFilters(): void
    {
        $this->school = '';
        $this->category = '';
        $this->when = 'upcoming';
        $this->limit = self::PAGE;
    }

    public function showMore(): void
    {
        $this->limit = min($this->limit + self::PAGE, 60);
    }

    public function preview(string $slug): void
    {
        $this->previewSlug = $this->schools->firstWhere('slug', $slug)?->slug;
        unset($this->previewSchool);
        if ($this->previewSlug) {
            Flux::modal('school-preview')->show();
        }
    }

    #[Computed]
    public function schools()
    {
        return School::query()->publiclyListed()
            ->withCount(['offers as offers_count' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['events as upcoming_count' => fn ($q) => $q->where('visibility', 'public')->where('status', '!=', 'cancelled')->upcoming()])
            ->with('logoMedia')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'slug', 'city', 'tagline', 'color', 'logo_path', 'logo_media_id', 'settings', 'updated_at']);
    }

    private function schoolId(): ?int
    {
        return $this->schools->firstWhere('slug', $this->school)?->id;
    }

    private function categoryId(): ?int
    {
        return ctype_digit($this->category) ? (int) $this->category : null;
    }

    private function upcomingPublic(): Builder
    {
        return Event::query()->publiclyVisible()
            ->with('school:id,name,short_name,slug,color,logo_media_id,logo_path')
            ->when($this->schoolId(), fn ($q, $id) => $q->where('events.school_id', $id))
            ->upcoming();
    }

    /**
     * Categories that have something coming up (with counts), so the filter
     * never offers an empty choice. Counts follow the school filter.
     *
     * @return Collection<int, Term>
     */
    #[Computed]
    public function categories(): Collection
    {
        $counts = $this->upcomingPublic()->withoutEagerLoads()->reorder()
            ->select('events.category_id')->selectRaw('count(*) as aggregate')->groupBy('events.category_id')
            ->pluck('aggregate', 'category_id')->filter(fn ($v, $k) => $k !== '' && $k !== null);

        return Term::query()->ofKind(TermKind::EventCategory)
            ->whereIn('id', $counts->keys()->merge(array_filter([$this->categoryId()]))->all())
            ->ordered()->get(Term::PUBLIC_COLUMNS)
            ->each(fn (Term $t) => $t->setAttribute('upcoming_count', (int) ($counts[$t->id] ?? 0)));
    }

    /**
     * Bento: the next events from highlighted categories (school filter only;
     * hidden while a category filter is active so nothing appears twice).
     */
    #[Computed]
    public function featured(): Collection
    {
        if ($this->categoryId()) {
            return collect();
        }

        return $this->upcomingPublic()
            ->whereHas('category', fn ($q) => $q->where('is_featured', true))
            ->with('cover')
            ->orderBy('events.starts_at')->limit(3)->get();
    }

    #[Computed]
    public function pinned(): Collection
    {
        return Announcement::query()->publiclyVisible()
            ->with('school:id,name,short_name,slug,color,logo_media_id,logo_path')
            ->when($this->schoolId(), fn ($q, $id) => $q->where('announcements.school_id', $id))
            ->orderByRaw('case when announcements.pinned_until > ? then 0 else 1 end', [now()])
            ->latest('published_at')
            ->limit(6)
            ->get(['id', 'school_id', 'title', 'body', 'priority', 'published_at', 'pinned_until']);
    }

    private function eventsQuery(): Builder
    {
        return $this->upcomingPublic()
            ->when($this->categoryId(), fn ($q, $id) => $q->where('events.category_id', $id))
            ->when($this->when === 'today', fn ($q) => $q->where('events.starts_at', '<=', now()->endOfDay()))
            ->when($this->when === 'week', fn ($q) => $q->where('events.starts_at', '<=', now()->endOfWeek()))
            ->when($this->when === 'month', fn ($q) => $q->where('events.starts_at', '<=', now()->endOfMonth()))
            ->whereNotIn('events.id', $this->featured->pluck('id')->all());
    }

    #[Computed]
    public function events(): Collection
    {
        return $this->eventsQuery()->orderBy('events.starts_at')->limit($this->limit + 1)->get();
    }

    /**
     * Live summary for the hero (all public, all listed schools).
     *
     * @return array{today: int, week: int, schools: int, announcements: int}
     */
    #[Computed]
    public function stats(): array
    {
        $base = fn () => Event::query()->publiclyVisible()->withoutEagerLoads()->upcoming();

        return [
            'today' => $base()->where('events.starts_at', '<=', now()->endOfDay())->count(),
            'week' => $base()->where('events.starts_at', '<=', now()->endOfWeek())->count(),
            'schools' => $this->schools->count(),
            'announcements' => Announcement::query()->publiclyVisible()->count(),
        ];
    }

    #[Computed]
    public function previewSchool(): ?School
    {
        if (! $this->previewSlug) {
            return null;
        }

        $school = School::query()->publiclyListed()->where('slug', $this->previewSlug)->with('logoMedia')->first();
        if ($school) {
            $features = app(Features::class);
            $school->setRelation('offers', $features->enabled(Feature::PublicOffers, $school)
                ? $school->offers()->where('is_active', true)->limit(8)->get()
                : collect());
            $school->setRelation('events', Event::query()->publiclyVisible()->where('events.school_id', $school->id)
                ->upcoming()->orderBy('events.starts_at')->limit(3)->get());
        }

        return $school;
    }

    public function joinWithCode()
    {
        $code = preg_replace('/[^A-Za-z0-9]/', '', $this->code);
        if (strlen($code) < 32) {
            $this->addError('code', __('To nie wygląda na kod zaproszenia. Sprawdź plakat lub e-mail.'));

            return null;
        }

        return $this->redirectRoute('join.show', $code);
    }

    public function render()
    {
        $features = app(Features::class);

        return view('livewire.public.home', [
            'filtered' => $this->school !== '' || $this->category !== '' || $this->when !== 'upcoming',
            'whenOptions' => ['upcoming' => __('Wszystkie'), 'today' => __('Dziś'), 'week' => __('Ten tydzień'), 'month' => __('Ten miesiąc')],
            'tvEnabled' => $features->enabled(Feature::TvMode, null),
            'icsEnabled' => fn (School $s) => $features->enabled(Feature::EventIcs, $s),
            'offersEnabled' => fn (School $s) => $features->enabled(Feature::PublicOffers, $s),
            'excerpt' => fn (string $html, int $length = 160) => RichText::excerpt(Mentions::forPublic($html), $length),
        ]);
    }
}
