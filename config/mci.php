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
        'school_name' => 'Szkolny Ośrodek Wiadomości i Alarmowania',
        'short_name' => 'SOWA',
        'primary_color' => '#1d4ed8',
        'primary_foreground' => '#ffffff',
        'danger_color' => '#dc2626',
        'radius' => 'md',
        'default_theme' => 'system',
        'logo_path' => null,
        'logo_media_id' => null,
        'favicon_media_id' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media (images). Files are re-encoded with GD (metadata stripped) and
    | downscaled per collection; WebP with alpha when GD supports it.
    |--------------------------------------------------------------------------
    */

    'media' => [
        'max_upload_kb' => (int) env('MCI_MEDIA_MAX_UPLOAD_KB', 8192),
        'max_pixels' => 40_000_000,
        'quality' => 85,
        'max_post_images' => 6,
        'collections' => [
            'logo' => 1024,
            'favicon' => 512,
            'cover' => 2000,
            'image' => 2000,
        ],
    ],

    /*
    | Demo mode (site admin → Tryb demo). Demo data lives in a separate
    | database (connection "demo"), never in the production one; a flag file
    | outside both databases switches the whole site. The demo database name
    | must differ from the main one and contain "demo".
    */
    'demo' => [
        'main_connection' => env('DB_CONNECTION', 'mysql'),
        'database' => env('DB_DEMO_DATABASE'),
        'flag' => env('MCI_DEMO_FLAG', storage_path('app/demo-mode.json')),
        'admin_email' => env('MCI_DEMO_ADMIN_EMAIL', 'demo@example.edu.pl'),
        'admin_password' => env('MCI_DEMO_ADMIN_PASSWORD', 'demo12345'),
        // The demo database holds one school with every module filled in.
        'single_school' => true,
    ],

    /*
    | Calendar on the dashboard: "personal" (my tasks, my projects, my
    | decisions + school events) or "school" (everything of the school).
    | Schools may change it (Ustawienia szkoły), people in their profile.
    */
    'calendar' => [
        'default' => env('MCI_CALENDAR_DEFAULT', 'personal'),
    ],

    /*
    | TV board (/tv): screens shown in turn and how long each stays, in
    | seconds. Site admins change it in Witryna → Tryb TV, schools may
    | override it, and a single screen may override both with
    | ?ekrany=agenda:20,calendar:30 in its URL.
    */
    'tv' => [
        'screens' => ['agenda' => 20, 'calendar' => 20, 'projects' => 20, 'announcements' => 15],
        'min_seconds' => 5,
        'max_seconds' => 600,
    ],

    /*
    | To-Do (Step 5): default reminder before the due time, in minutes, for
    | tasks with an hour and for all-day tasks; overdue reminders are sent
    | once a day, only between these hours (Europe/Warsaw).
    */
    'tasks' => [
        'remind_before_minutes' => (int) env('MCI_TASK_REMIND_MINUTES', 60),
        'remind_before_minutes_all_day' => (int) env('MCI_TASK_REMIND_MINUTES_ALL_DAY', 24 * 60),
        'overdue_hours' => [7, 20],
        'max_checklist_items' => 30,
    ],

];
