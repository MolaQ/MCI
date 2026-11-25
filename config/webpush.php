<?php

use App\Models\PushSubscription;

/*
| Web Push (VAPID). Generate keys once with `php artisan webpush:vapid`
| (or --show to print them) and keep them: changing them invalidates every
| existing subscription. Without keys the app simply does not offer push.
*/
return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],
    'model' => PushSubscription::class,
    'table_name' => 'push_subscriptions',
    'database_connection' => null,
    'client_options' => ['timeout' => 10],
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),
];
