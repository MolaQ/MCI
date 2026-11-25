<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Real-time mode
    |--------------------------------------------------------------------------
    |
    | "poll"      — Livewire polling (works on any shared PHP hosting).
    | "broadcast" — WebSockets via Laravel Reverb / Pusher (VPS only).
    |
    */

    'realtime' => env('MCI_REALTIME', 'poll'),

    'poll_seconds' => (int) env('MCI_POLL_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Queue processing on shared hosting
    |--------------------------------------------------------------------------
    |
    | When true, the scheduler (cron: * * * * * php artisan schedule:run)
    | drains the database queue every minute with --stop-when-empty, so no
    | long-running worker process is required.
    |
    */

    'queue_via_scheduler' => (bool) env('MCI_QUEUE_VIA_SCHEDULER', true),

    'queue_max_time' => (int) env('MCI_QUEUE_MAX_TIME', 50),

    'queues' => ['alerts', 'push', 'default', 'mail'],

    /*
    |--------------------------------------------------------------------------
    | First administrator (used by the database seeder)
    |--------------------------------------------------------------------------
    */

    'admin' => [
        'email' => env('MCI_ADMIN_EMAIL'),
        'password' => env('MCI_ADMIN_PASSWORD'),
        'first_name' => env('MCI_ADMIN_FIRST_NAME', 'Administrator'),
        'last_name' => env('MCI_ADMIN_LAST_NAME', 'MCI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance defaults (overridable in Administracja → Wygląd)
    |--------------------------------------------------------------------------
    */

    'appearance' => [
        'school_name' => 'Mechaniczne Centrum Informacyjne',
        'short_name' => 'MCI',
        'primary_color' => '#1d4ed8',
        'primary_foreground' => '#ffffff',
        'danger_color' => '#dc2626',
        'radius' => 'md',
        'default_theme' => 'system',
        'logo_path' => null,
    ],

];
