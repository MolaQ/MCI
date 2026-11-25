<?php

namespace App\Listeners;

use App\Models\PushSubscription;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

/**
 * Keeps "last delivered" / "last failed" per device for Ustawienia →
 * Powiadomienia. Expired endpoints (404/410) are deleted by the channel.
 */
class RecordPushResult
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        $subscription = $event->subscription;
        if (! $subscription instanceof PushSubscription || ! $subscription->exists) {
            return;
        }

        $subscription->forceFill($event instanceof NotificationSent
            ? ['last_success_at' => now(), 'failed_at' => null]
            : ['failed_at' => now()])->saveQuietly();
    }
}
