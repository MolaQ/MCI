<?php

namespace App\Livewire\Communication;

use App\Models\Announcement;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $announcementId;

    public string $statsFilter = 'unread';

    public function mount(Announcement $announcement): void
    {
        $this->authorize('view', $announcement);
        $this->announcementId = $announcement->id;

        // Opening it marks it as read.
        DB::table('announcement_recipients')
            ->where('announcement_id', $announcement->id)
            ->where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Auth::user()->unreadNotifications()
            ->where('data->announcement_id', $announcement->id)
            ->update(['read_at' => now()]);
    }

    #[Computed]
    public function announcement(): Announcement
    {
        return Announcement::query()->with(['author', 'school', 'audience'])->findOrFail($this->announcementId);
    }

    #[Computed]
    public function receipt(): ?object
    {
        return DB::table('announcement_recipients')
            ->where('announcement_id', $this->announcementId)
            ->where('user_id', Auth::id())
            ->first();
    }

    #[Computed]
    public function stats(): ?array
    {
        if (! Auth::user()->can('viewStats', $this->announcement)) {
            return null;
        }

        $rows = DB::table('announcement_recipients')->where('announcement_id', $this->announcementId);

        return [
            'total' => (clone $rows)->count(),
            'read' => (clone $rows)->whereNotNull('read_at')->count(),
            'confirmed' => (clone $rows)->whereNotNull('confirmed_at')->count(),
        ];
    }

    #[Computed]
    public function people()
    {
        if (! $this->stats) {
            return collect();
        }

        return $this->announcement->recipients()
            ->when($this->statsFilter === 'unread', fn ($q) => $q->whereNull('announcement_recipients.read_at'))
            ->when($this->statsFilter === 'read', fn ($q) => $q->whereNotNull('announcement_recipients.read_at'))
            ->when($this->statsFilter === 'unconfirmed', fn ($q) => $q->whereNull('announcement_recipients.confirmed_at'))
            ->when($this->statsFilter === 'confirmed', fn ($q) => $q->whereNotNull('announcement_recipients.confirmed_at'))
            ->orderBy('last_name')->limit(200)->get();
    }

    public function confirm(): void
    {
        $this->authorize('view', $this->announcement);

        $updated = DB::table('announcement_recipients')
            ->where('announcement_id', $this->announcementId)
            ->where('user_id', Auth::id())
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => now(), 'read_at' => DB::raw('COALESCE(read_at, CURRENT_TIMESTAMP)')]);

        if ($updated) {
            Flux::toast(variant: 'success', text: __('Dziękujemy — potwierdzono zapoznanie się.'));
        }
        unset($this->receipt, $this->stats);
    }

    public function withdraw()
    {
        $this->authorize('delete', $this->announcement);

        Audit::log('announcement.withdrawn', $this->announcement, ['title' => $this->announcement->title], null, $this->announcement->school_id);
        $this->announcement->delete();

        Flux::toast(text: __('Komunikat wycofano.'));

        return $this->redirectRoute('communication.index', navigate: true);
    }

    public function render()
    {
        $this->authorize('view', $this->announcement);

        return view('livewire.communication.show')->title($this->announcement->title);
    }
}
