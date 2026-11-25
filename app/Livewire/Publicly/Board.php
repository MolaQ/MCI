<?php

namespace App\Livewire\Publicly;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\School;
use App\Support\Mentions;
use App\Support\RichText;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public TV / full-screen board (docs/ARCHITECTURE.md §12.3): today and the
 * coming days, plus public announcements. Unattended screens need no login;
 * internal boards with alerts come with the TV-mode step.
 */
#[Layout('layouts::tv')]
#[Title('Tablica')]
class Board extends Component
{
    #[Url(as: 'szkola', except: '')]
    public string $school = '';

    public function render()
    {
        $school = $this->school !== '' ? School::query()->publiclyListed()->where('slug', $this->school)->first() : null;

        $events = Event::query()->publiclyVisible()
            ->with('school:id,name,short_name,slug,color')
            ->when($school, fn ($q) => $q->where('events.school_id', $school->id))
            ->upcoming()
            ->where('events.starts_at', '<=', now()->addDays(14)->endOfDay())
            ->orderBy('events.starts_at')
            ->limit(9)
            ->get();

        return view('livewire.public.board', [
            'currentSchool' => $school,
            'today' => $events->filter(fn ($e) => $e->starts_at->isToday() || ($e->ends_at && now()->between($e->starts_at, $e->ends_at))),
            'upcoming' => $events->reject(fn ($e) => $e->starts_at->isToday() || ($e->ends_at && now()->between($e->starts_at, $e->ends_at))),
            'announcements' => Announcement::query()->publiclyVisible()
                ->with('school:id,short_name,color')
                ->when($school, fn ($q) => $q->where('announcements.school_id', $school->id))
                ->latest('published_at')->limit(3)->get(['id', 'school_id', 'title', 'body', 'priority', 'published_at']),
            'excerpt' => fn (string $html) => RichText::excerpt(Mentions::forPublic($html), 260),
            'pollSeconds' => max(30, 3 * (int) config('mci.feed_poll_seconds', 20)),
        ]);
    }
}
