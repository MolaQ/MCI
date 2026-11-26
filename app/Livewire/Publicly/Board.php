<?php

namespace App\Livewire\Publicly;

use App\Domain\Calendar\Agenda;
use App\Enums\AnnouncementPriority;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\School;
use App\Support\Mentions;
use App\Support\RichText;
use App\Support\TvScreens;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public TV / full-screen board (docs/ARCHITECTURE.md §12.3). Screens
 * rotate on their own: today's agenda, the month calendar, projects
 * stretched over time and announcements, each for its configured number
 * of seconds (site → school → ?ekrany= of this board). Public data only;
 * data refreshes by polling, the rotation runs in the browser.
 */
#[Layout('layouts::tv')]
#[Title('Tablica')]
class Board extends Component
{
    #[Url(as: 'szkola', except: '')]
    public string $school = '';

    #[Url(as: 'ekrany', except: '')]
    public string $screensParam = '';

    private function currentSchool(): ?School
    {
        if ($this->school !== '') {
            return School::query()->publiclyListed()->where('slug', $this->school)->first();
        }
        $listed = School::query()->publiclyListed()->limit(2)->get();

        return $listed->count() === 1 ? $listed->first() : null;
    }

    public function render(Agenda $agenda, TvScreens $tv)
    {
        $school = $this->currentSchool();
        $schoolId = $school?->id;
        $today = now()->startOfDay();

        $todayItems = collect(Agenda::byDay($agenda->forPublic($schoolId, $today, now()->endOfDay()), $today, now()->endOfDay())[$today->toDateString()] ?? []);
        $month = CarbonImmutable::now()->startOfMonth();
        $grid = Agenda::monthGrid($month);
        $monthDays = Agenda::byDay($agenda->forPublic($schoolId, $grid[0][0], end($grid)[6]->endOfDay()), $grid[0][0], end($grid)[6]->endOfDay());
        $from = CarbonImmutable::now()->startOfWeek()->subWeeks(1);
        $to = $from->addWeeks(13)->endOfDay();
        $projects = $agenda->publicProjects($schoolId, $from, $to);

        $announcements = Announcement::query()->publiclyVisible()
            ->with('school:id,short_name,color,logo_media_id')
            ->when($schoolId, fn ($q) => $q->where('announcements.school_id', $schoolId))
            ->orderByRaw('case when pinned_until is not null and pinned_until > ? then 0 else 1 end', [now()])
            ->latest('published_at')->limit(6)->get(['id', 'school_id', 'title', 'body', 'priority', 'published_at', 'pinned_until']);

        $upcoming = Event::query()->publiclyVisible()
            ->with('school:id,short_name')
            ->when($schoolId, fn ($q) => $q->where('events.school_id', $schoolId))
            ->where('events.starts_at', '>', now()->endOfDay())
            ->where('events.starts_at', '<=', now()->addDays(14)->endOfDay())
            ->orderBy('events.starts_at')->limit(5)->get();

        // Screens with nothing to show are skipped (the agenda always stays).
        $screens = array_values(array_filter($tv->resolve($school, $this->screensParam ?: null), fn ($s) => match ($s['key']) {
            'projects' => $projects->isNotEmpty(),
            'announcements' => $announcements->isNotEmpty(),
            default => true,
        })) ?: [['key' => 'agenda', 'seconds' => 30]];

        return view('livewire.public.board', [
            'currentSchool' => $school,
            'screens' => $screens,
            'catalog' => TvScreens::catalog(),
            'todayItems' => $todayItems,
            'upcoming' => $upcoming,
            'alerts' => $announcements->filter(fn ($a) => $a->priority !== AnnouncementPriority::Normal || $a->isPinned())->take(3),
            'month' => $month,
            'monthDays' => $monthDays,
            'projects' => $projects,
            'from' => $from,
            'to' => $to,
            'announcements' => $announcements,
            'excerpt' => fn (string $html, int $len = 220) => RichText::excerpt(Mentions::forPublic($html), $len),
            'pollSeconds' => max(30, 3 * (int) config('mci.feed_poll_seconds', 20)),
        ]);
    }
}
