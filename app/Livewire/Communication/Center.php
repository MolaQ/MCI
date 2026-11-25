<?php

namespace App\Livewire\Communication;

use App\Enums\AnnouncementPriority;
use App\Models\Announcement;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Personal communication centre: what was sent *to me* in the active school
 * (or in all my schools), with button filters and live refresh by polling.
 */
#[Title('Komunikaty')]
class Center extends Component
{
    use WithPagination;

    public const FILTERS = ['all', 'unread', 'important', 'confirm', 'mentions', 'sent'];

    #[Url(as: 'widok', except: 'all')]
    public string $filter = 'all';

    #[Url(as: 'szkoly', except: false)]
    public bool $allSchools = false;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['filter', 'allSchools', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function setFilter(string $filter): void
    {
        abort_unless(in_array($filter, self::FILTERS, true), 400);
        $this->filter = $filter;
        $this->resetPage();
    }

    private function received(): Builder
    {
        $user = Auth::user();
        $schoolId = app(CurrentSchool::class)->id();

        return Announcement::query()
            ->join('announcement_recipients as r', fn ($j) => $j->on('r.announcement_id', '=', 'announcements.id')->where('r.user_id', $user->id))
            ->select('announcements.*', 'r.read_at', 'r.confirmed_at', 'r.reason')
            ->live()
            ->when(! $this->allSchools, fn ($q) => $q->where('announcements.school_id', $schoolId));
    }

    #[Computed]
    public function counts(): array
    {
        $base = $this->received();

        return [
            'unread' => (clone $base)->whereNull('r.read_at')->count(),
            'important' => (clone $base)->whereIn('announcements.priority', [AnnouncementPriority::Important->value, AnnouncementPriority::Urgent->value])->whereNull('r.read_at')->count(),
            'confirm' => (clone $base)->where('announcements.requires_confirmation', true)->whereNull('r.confirmed_at')->count(),
            'mentions' => (clone $base)->where('r.reason', 'mention')->whereNull('r.read_at')->count(),
        ];
    }

    #[Computed]
    public function items()
    {
        if ($this->filter === 'sent') {
            return Announcement::query()
                ->where('author_id', Auth::id())
                ->when(! $this->allSchools, fn ($q) => $q->where('school_id', app(CurrentSchool::class)->id()))
                ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->with('school')
                ->withCount(['recipients as read_count' => fn ($q) => $q->whereNotNull('announcement_recipients.read_at')])
                ->latest('published_at')
                ->paginate(15);
        }

        return $this->received()
            ->with(['author', 'school'])
            ->when($this->filter === 'unread', fn ($q) => $q->whereNull('r.read_at'))
            ->when($this->filter === 'important', fn ($q) => $q->whereIn('announcements.priority', [AnnouncementPriority::Important->value, AnnouncementPriority::Urgent->value]))
            ->when($this->filter === 'confirm', fn ($q) => $q->where('announcements.requires_confirmation', true)->whereNull('r.confirmed_at'))
            ->when($this->filter === 'mentions', fn ($q) => $q->where('r.reason', 'mention'))
            ->when($this->search !== '', fn ($q) => $q->where('announcements.title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%'))
            ->orderByRaw('CASE WHEN announcements.pinned_until > ? THEN 0 ELSE 1 END', [now()])
            ->orderByDesc('announcements.published_at')
            ->paginate(15);
    }

    public function markAllRead(): void
    {
        $ids = $this->received()->whereNull('r.read_at')->pluck('announcements.id');

        DB::table('announcement_recipients')->where('user_id', Auth::id())->whereIn('announcement_id', $ids)->update(['read_at' => now()]);
        unset($this->counts, $this->items);
    }

    public function render()
    {
        return view('livewire.communication.center', [
            'canCompose' => Auth::user()->can('create', Announcement::class),
            'pollSeconds' => max(10, (int) config('mci.feed_poll_seconds', 20)),
        ]);
    }
}
