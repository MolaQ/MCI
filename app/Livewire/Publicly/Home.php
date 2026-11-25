<?php

namespace App\Livewire\Publicly;

use App\Enums\EventCategory;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\School;
use App\Support\Mentions;
use App\Support\RichText;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public front (no login): participating schools, their offer and public
 * events / announcements. Every query goes through a "publiclyVisible"
 * scope, which selects public columns only.
 */
#[Layout('layouts::public')]
#[Title('Portal szkół')]
class Home extends Component
{
    #[Url(as: 'szkola', except: '')]
    public string $school = '';

    #[Url(as: 'kategoria', except: '')]
    public string $category = '';

    #[Url(as: 'kiedy', except: 'upcoming')]
    public string $when = 'upcoming';

    public string $code = '';

    #[Computed]
    public function schools()
    {
        return School::query()->publiclyListed()
            ->withCount(['offers as offers_count' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['events as upcoming_count' => fn ($q) => $q->where('visibility', 'public')->where('status', '!=', 'cancelled')->upcoming()])
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'slug', 'city', 'tagline', 'color', 'logo_path']);
    }

    #[Computed]
    public function events()
    {
        $schoolId = $this->schools->firstWhere('slug', $this->school)?->id;

        return Event::query()->publiclyVisible()
            ->with('school:id,name,short_name,slug,color')
            ->when($schoolId, fn ($q) => $q->where('events.school_id', $schoolId))
            ->when(EventCategory::tryFrom($this->category), fn ($q, $c) => $q->where('events.category', $c->value))
            ->when($this->when === 'week', fn ($q) => $q->whereBetween('events.starts_at', [now()->startOfDay(), now()->endOfWeek()]))
            ->when($this->when === 'month', fn ($q) => $q->whereBetween('events.starts_at', [now()->startOfDay(), now()->endOfMonth()]))
            ->upcoming()
            ->orderBy('events.starts_at')
            ->limit(24)
            ->get();
    }

    #[Computed]
    public function announcements()
    {
        return Announcement::query()->publiclyVisible()
            ->with('school:id,name,short_name,slug,color')
            ->latest('published_at')
            ->limit(6)
            ->get(['id', 'school_id', 'title', 'body', 'priority', 'published_at']);
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
        return view('livewire.public.home', [
            'categories' => EventCategory::cases(),
            'excerpt' => fn (string $html) => RichText::excerpt(Mentions::forPublic($html), 200),
        ]);
    }
}
