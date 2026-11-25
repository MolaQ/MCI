<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\User;
use App\Support\Push;
use App\Support\RichText;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A post reaching one recipient.
 *  - bell (database): mentions, important/urgent posts, posts asking for confirmation;
 *  - Web Push: the same moments plus, if the person opted in, routine posts —
 *    each kind controlled in Ustawienia → Powiadomienia (User::PUSH_KINDS).
 */
class AnnouncementNotification extends Notification
{
    public function __construct(public Announcement $announcement, public bool $mentioned = false, public bool $bell = true) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->bell ? ['database'] : [];

        if ($notifiable instanceof User && $this->wantsPush($notifiable)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    private function wantsPush(User $user): bool
    {
        $a = $this->announcement;
        if (! Push::enabled($a->school_id) || $user->pushSubscriptions->isEmpty()) {
            return false;
        }

        return ($this->mentioned && $user->wantsPush('mentions'))
            || ($a->priority->notifiesInBell() && $user->wantsPush('important'))
            || ($a->requires_confirmation && $user->wantsPush('confirmations'))
            || $user->wantsPush('announcements');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $a = $this->announcement;

        return [
            'kind' => $this->mentioned ? 'mention' : 'announcement',
            'title' => $this->title(),
            'body' => $a->title,
            'school' => $a->school?->short_name,
            'priority' => $a->priority->value,
            'url' => route('communication.show', $a),
            'announcement_id' => $a->id,
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        $a = $this->announcement;
        $urgent = $a->priority->value === 'urgent';

        return (new WebPushMessage)
            ->title(($a->school?->short_name ? $a->school->short_name.' · ' : '').$this->title())
            ->body($a->title."\n".RichText::excerpt($a->body, 120))
            ->icon('/pwa/icon-192.png')
            ->badge('/pwa/icon-96-badge.png')
            ->tag('announcement-'.$a->id)
            ->lang('pl')
            ->requireInteraction($urgent || $a->requires_confirmation)
            ->data(['url' => route('communication.show', $a, absolute: false)])
            ->options(['TTL' => $urgent ? 86400 : 259200, 'urgency' => $urgent ? 'high' : 'normal', 'topic' => 'announcement-'.$a->id]);
    }

    private function title(): string
    {
        $a = $this->announcement;

        if ($this->mentioned) {
            return __(':author wspomniał(a) o Tobie', ['author' => $a->author?->name ?? __('Ktoś')]);
        }

        return $a->priority->notifiesInBell()
            ? __('Nowy komunikat: :priority', ['priority' => mb_strtolower($a->priority->label())])
            : ($a->requires_confirmation ? __('Komunikat do potwierdzenia') : __('Nowy komunikat'));
    }
}
