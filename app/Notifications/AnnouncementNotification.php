<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;

/**
 * In-app (bell) notification: a new important post, or "@you" in a post.
 */
class AnnouncementNotification extends Notification
{
    public function __construct(public Announcement $announcement, public bool $mentioned = false) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $a = $this->announcement;

        return [
            'kind' => $this->mentioned ? 'mention' : 'announcement',
            'title' => $this->mentioned
                ? __(':author wspomniał(a) o Tobie', ['author' => $a->author?->name ?? __('Ktoś')])
                : __('Nowy komunikat: :priority', ['priority' => mb_strtolower($a->priority->label())]),
            'body' => $a->title,
            'school' => $a->school?->short_name,
            'priority' => $a->priority->value,
            'url' => route('communication.show', $a),
            'announcement_id' => $a->id,
        ];
    }
}
