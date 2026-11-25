<?php

namespace App\Models;

use NotificationChannels\WebPush\PushSubscription as BaseSubscription;

/**
 * One browser/device that receives Web Push for a user. Dead endpoints
 * (HTTP 404/410) are deleted by the channel; failures and successes are
 * recorded by App\Listeners\RecordPushResult.
 */
class PushSubscription extends BaseSubscription
{
    protected $fillable = [
        'endpoint', 'public_key', 'auth_token', 'content_encoding',
        'device_label', 'platform', 'last_success_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_success_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
