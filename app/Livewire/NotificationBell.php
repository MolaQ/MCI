<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The bell: important posts, mentions and (later) reminders. Refreshes by
 * polling, so it works on shared hosting without WebSockets.
 */
class NotificationBell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed]
    public function latest()
    {
        return Auth::user()->notifications()->latest()->limit(8)->get();
    }

    public function open(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) data_get($notification->data, 'url', route('dashboard'));

        // Only ever follow links into this application.
        return $this->redirect(str_starts_with($url, url('/')) ? $url : route('dashboard'), navigate: true);
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        unset($this->unreadCount, $this->latest);
    }

    public function render()
    {
        return view('livewire.notification-bell', [
            'pollSeconds' => max(10, (int) config('mci.feed_poll_seconds', 20)),
        ]);
    }
}
