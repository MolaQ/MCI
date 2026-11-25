<?php

return [
    'user_status' => [
        'invited' => 'Oczekuje',
        'active' => 'Aktywne',
        'suspended' => 'Zawieszone',
    ],
    'group_role' => [
        'member' => 'Członek',
        'leader' => 'Lider',
    ],
    'membership_mode' => [
        'managed' => 'Zarządzana przez liderów',
        'invite' => 'Przez zaproszenie / QR',
        'open' => 'Otwarta (każdy może dołączyć)',
    ],
    'role' => [
        'admin' => 'Administrator',
        'coordinator' => 'Koordynator',
        'teacher' => 'Nauczyciel',
        'staff' => 'Pracownik',
        'student' => 'Uczeń',
    ],
    'profile_visibility' => [
        'school' => 'Tylko moje szkoły',
        'network' => 'Wszystkie szkoły w MCI',
        'public' => 'Publicznie',
    ],
    'profile_visibility_help' => [
        'school' => 'Profil widzą tylko osoby z Twoich szkół.',
        'network' => 'Profil widzą też zalogowani użytkownicy innych szkół (np. przy wspólnych wydarzeniach).',
        'public' => 'Imię, nazwisko i szkoła mogą pojawić się na stronach publicznych (np. jako kontakt do wydarzenia).',
    ],
    'invite_kind' => [
        'personal' => 'Imienne',
        'group_link' => 'Link / kod QR dla grupy',
    ],
    'permission' => [
        'users_manage' => 'Zarządzanie kontami',
        'roles_manage' => 'Nadawanie ról i uprawnień',
        'groups_manage' => 'Zarządzanie wszystkimi grupami',
        'categories_manage' => 'Kategorie wydarzeń i typy grup szkoły',
        'invites_create' => 'Tworzenie zaproszeń / kodów QR',
        'announcements_publish_any' => 'Ogłoszenia do dowolnych odbiorców',
        'announcements_publish_own_groups' => 'Ogłoszenia do własnych grup',
        'alerts_send' => 'Wysyłanie alarmów bezpieczeństwa',
        'alerts_send_drill' => 'Wysyłanie alarmów ćwiczebnych',
        'alerts_view_all_stats' => 'Statystyki wszystkich alarmów',
        'events_create' => 'Tworzenie wydarzeń',
        'events_publish_public' => 'Publikowanie treści publicznych (wydarzenia, komunikaty)',
        'tasks_assign_any' => 'Przydzielanie zadań dowolnym osobom',
        'stats_view' => 'Podgląd statystyk',
        'audit_view' => 'Podgląd dziennika zdarzeń',
        'settings_manage' => 'Ustawienia systemu i wygląd',
    ],

    'announcement_priority' => [
        'normal' => 'Zwykły',
        'important' => 'Ważny',
        'urgent' => 'Pilny',
    ],

    'visibility' => [
        'public' => 'Publiczne',
        'inter_school' => 'Międzyszkolne',
        'school_only' => 'Tylko szkoła',
        'private_group' => 'Wybrane osoby',
    ],

    'visibility_help' => [
        'public' => 'Widoczne dla wszystkich na stronie głównej, bez logowania.',
        'inter_school' => 'Widoczne dla zalogowanych osób ze wszystkich szkół portalu.',
        'school_only' => 'Widoczne tylko dla członków tej szkoły.',
        'private_group' => 'Tylko wybrane grupy i osoby.',
    ],

    'offer_kind' => [
        'technikum' => 'Technikum',
        'branzowa' => 'Szkoła branżowa',
        'liceum' => 'Liceum',
        'policealna' => 'Szkoła policealna',
        'kurs' => 'Kurs / KKZ',
        'inne' => 'Inne',
    ],

];
