<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TestPushNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('Powiadomienie testowe'))
            ->body(__('Działa! Tak będą wyglądać komunikaty i alarmy. Wysłano :time.', ['time' => now()->format('H:i')]))
            ->icon('/pwa/icon-192.png')
            ->badge('/pwa/icon-96-badge.png')
            ->tag('mci-test')
            ->lang('pl')
            ->data(['url' => route('settings.notifications', absolute: false)]);
    }
}
