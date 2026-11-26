<?php

namespace App\Livewire\Publicly;

use App\Enums\Feature;
use App\Enums\TaxonomyKind;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\School;
use App\Models\TaxonomyTerm;
use App\Support\Features;
use App\Support\Mentions;
use App\Support\RichText;
use App\Support\Taxonomy;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public front (no login), an information centre: identity + live summary,
 * one row of filters (school, category, time), a featured grid of public
 * events and announcements, and the school directory with a quick preview.
 * Every query goes through a "publiclyVisible"/"publiclyListed" scope.
 */
#[Layout('layouts::public')]
#[Title('Portal szkół')]
class Home extends Component
{
    /** 1 featured + 6 tiles fill two rows of three. */
    private const PAGE = 7;

    #[Url(as: 'szkola', except: '')]
    public string $school = '';

    #[Url(as: 'kategoria', except: '')]
    public string $category = '';

    #[Url(as: 'kiedy', except: 'upcoming')]
    public string $when = 'upcoming';

    public int $limit = self::PAGE;

    public string $code = '';

    public ?string $preview = null;

    /** @return array<string, string> */
    public static function timeframes(): array
    {
        return ['upcoming' => __('Nadchodzące'), 'week' => __('Ten tydzień'), 'month' => __('Ten miesiąc')];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['school', 'category', 'when'], true)) {
            $this->limit = self::PAGE;
        }
    }

    public function setFilter(string $filter, string $value): void
    {
        abort_unless(in_array($filter, ['school', 'category', 'when'], true), 422);
        if ($filter === 'when' && ! array_key_exists($value, self::timeframes())) {
            $value = 'upcoming';
        }
        $this->{$filter} = $value;
        $this->limit = self::PAGE;
    }

    public function resetFilters(): void
    {
        $this->reset('school', 'category', 'when', 'limit');
    }

    public function loadMore(): void
    {
        $this->limit = min($this->limit + 6, 61);
    }

    public function openSchool(string $slug): void
    {
        $this->preview = $this->schools->firstWhere('slug', $slug)?->slug;
        if ($this->preview) {
            unset($this->previewSchool);
            Flux::modal('school-preview')->show();
        }
    }

    #[Computed]
    public function schools(): Collection
    {
        return School::query()->publiclyListed()
            ->withCount(['offers as offers_count' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['events as upcoming_count' => fn ($q) => $q->where('visibility', 'public')->where('status', '!=', 'cancelled')->upcoming()])
            ->with('logoMedia')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'slug', 'city', 'tagline', 'color', 'logo_path', 'logo_media_id']);
    }

    private function schoolId(): ?int
    {
        return $this->schools->firstWhere('slug', $this->school)?->id;
    }

    /** Public, upcoming events in the chosen school and time frame (category applied separately). */
    private function baseEvents(): Builder
    {
        $schoolId = $this->schoolId();

        return Event::query()->publiclyVisible()
            ->when($schoolId, fn ($q) => $q->where('events.school_id', $schoolId))
            ->when($this->when === 'week', fn ($q) => $q->whereBetween('events.starts_at', [now()->startOfDay(), now()->endOfWeek()]))
            ->when($this->when === 'month', fn ($q) => $q->whereBetween('events.starts_at', [now()->startOfDay(), now()->endOfMonth()]))
            ->upcoming();
    }

    /**
     * Categories that actually have events for the other filters, with counts,
     * so the filter never offers an empty choice.
     *
     * @return Collection<int, array{term: TaxonomyTerm, count: int}>
     */
    #[Computed]
    public function categories(): Collection
    {
        $taxonomy = app(Taxonomy::class);

        return $this->baseEvents()->reorder()->toBase()
            ->select(['events.category', 'events.school_id'])->selectRaw('count(*) as aggregate')
            ->groupBy('events.category', 'events.school_id')->get()
            ->map(fn ($row) => ['term' => $taxonomy->resolve(TaxonomyKind::EventCategory, $row->category, $row->school_id), 'count' => (int) $row->aggregate])
            ->groupBy(fn ($r) => $r['term']->key)
            ->map(fn ($rows) => ['term' => $rows->first()['term'], 'count' => $rows->sum('count')])
            ->sortBy(fn ($r) => [$r['term']->school_id !== null, $r['term']->position])
            ->values();
    }

    #[Computed]
    public function events(): Collection
    {
        return $this->baseEvents()
            ->with(['school:id,name,short_name,slug,color,logo_media_id', 'school.logoMedia', 'cover'])
            ->when($this->category !== '', fn ($q) => $q->where('events.category', $this->category))
            ->orderBy('events.starts_at')
            ->limit($this->limit + 1)
            ->get();
    }

    #[Computed]
    public function announcements(): Collection
    {
        $schoolId = $this->schoolId();

        return Announcement::query()->publiclyVisible()
            ->with('school:id,name,short_name,slug,color,logo_media_id')
            ->when($schoolId, fn ($q) => $q->where('announcements.school_id', $schoolId))
            ->orderByRaw('case when pinned_until is not null and pinned_until > ? then 0 else 1 end', [now()])
            ->latest('published_at')
            ->limit(4)
            ->get(['id', 'school_id', 'title', 'body', 'priority', 'published_at', 'pinned_until']);
    }

    /** @return array{schools: int, today: int, week: int, announcements: int} */
    #[Computed]
    public function summary(): array
    {
        $public = fn () => Event::query()->publiclyVisible()->reorder()->toBase();

        return [
            'schools' => $this->schools->count(),
            'today' => $public()->where('events.starts_at', '<=', now()->endOfDay())->where(fn ($q) => $q
                ->where('events.starts_at', '>=', now()->startOfDay())->orWhere('events.ends_at', '>=', now()))->count(),
            'week' => $public()->whereBetween('events.starts_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])->count(),
            'announcements' => Announcement::query()->publiclyVisible()->toBase()->count(),
        ];
    }

    #[Computed]
    public function previewSchool(): ?array
    {
        $school = $this->preview ? School::query()->publiclyListed()->with('logoMedia')->where('slug', $this->preview)->first() : null;
        if (! $school) {
            return null;
        }

        $features = app(Features::class);

        return [
            'school' => $school,
            'offers' => $features->enabled(Feature::PublicOffers, $school) ? $school->offers()->where('is_active', true)->limit(8)->get() : collect(),
            'events' => Event::query()->publiclyVisible()->where('events.school_id', $school->id)->upcoming()->orderBy('events.starts_at')->limit(3)->get(),
            'ics' => $features->enabled(Feature::EventIcs, $school),
        ];
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

    public function mount(): void
    {
        // Portal switched off for the whole site: send people where they can go.
        if (app(Features::class)->disabled(Feature::PublicFront, null)) {
            $this->redirectRoute(auth()->check() ? 'dashboard' : 'login');
        }
        if (! array_key_exists($this->when, self::timeframes())) {
            $this->when = 'upcoming';
        }
    }

    /** The large tile shows the nearest event of a featured category (e.g. open days), else the nearest one. */
    private function featuredFirst(Collection $events): Collection
    {
        $featured = $events->first(fn (Event $e) => $e->category->is_featured);

        return $featured ? $events->reject(fn (Event $e) => $e->is($featured))->prepend($featured)->values() : $events->values();
    }

    public function render()
    {
        $events = $this->events;
        $activeCategory = $this->categories->firstWhere(fn ($r) => $r['term']->key === $this->category)['term'] ?? null;

        return view('livewire.public.home', [
            'list' => $this->featuredFirst($events->take($this->limit)),
            'hasMore' => $events->count() > $this->limit,
            'filtered' => $this->school !== '' || $this->category !== '' || $this->when !== 'upcoming',
            'schoolLabel' => $this->schools->firstWhere('slug', $this->school)?->short_name,
            'categoryLabel' => $activeCategory?->label(),
            'invites' => app(Features::class)->enabled(Feature::Invites, null),
            'tv' => app(Features::class)->enabled(Feature::TvMode, null),
            'excerpt' => fn (string $html, int $len = 160) => RichText::excerpt(Mentions::forPublic($html), $len),
        ]);
    }
}
