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

    // Feeds, notification bell and public boards refresh less often.
    'feed_poll_seconds' => (int) env('MCI_FEED_POLL_SECONDS', 20),

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
    | First school (used by the database seeder on a fresh install)
    |--------------------------------------------------------------------------
    */

    'school' => [
        'name' => env('MCI_SCHOOL_NAME', 'Zespół Szkół Mechanicznych'),
        'short_name' => env('MCI_SCHOOL_SHORT_NAME', 'ZSM'),
        'slug' => env('MCI_SCHOOL_SLUG'),
        'email_domains' => env('MCI_SCHOOL_EMAIL_DOMAINS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invites (Step 2)
    |--------------------------------------------------------------------------
    */

    'invites' => [
        'default_days' => (int) env('MCI_INVITE_DEFAULT_DAYS', 14),
        'max_days' => (int) env('MCI_INVITE_MAX_DAYS', 365),
        'verification_hours' => (int) env('MCI_SIGNUP_VERIFICATION_HOURS', 48),
    ],

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
