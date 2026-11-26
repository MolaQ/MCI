<?php

return [
    'user_status' => [
        'invited' => 'Oczekuje',
        'active' => 'Aktywne',
        'suspended' => 'Zawieszone',
    ],
    'group_type' => [
        'staff' => 'Kadra',
        'subject' => 'Przedmiotowa',
        'vocational' => 'Zawodowa',
        'class_tutors' => 'Wychowawcy',
        'class' => 'Klasa',
        'volunteering' => 'Wolontariat',
        'sport' => 'Sport',
        'club' => 'Koło / klub',
        'association' => 'Stowarzyszenie',
        'custom' => 'Inna',
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
        'network' => 'Wszystkie szkoły w portalu',
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
        'categories_manage' => 'Kategorie wydarzeń i typy grup',
        'workflows_manage' => 'Szablony obiegów i wniosków',
        'projects_manage' => 'Zarządzanie wszystkimi projektami szkoły',
    ],

    'workflow_status' => [
        'in_progress' => 'W toku',
        'returned' => 'Zwrócony do poprawy',
        'approved' => 'Zatwierdzony',
        'rejected' => 'Odrzucony',
        'cancelled' => 'Wycofany',
    ],

    'workflow_action' => [
        'submitted' => 'złożył(a) wniosek',
        'approved' => 'zatwierdził(a)',
        'rejected' => 'odrzucił(a)',
        'returned' => 'zwrócił(a) do poprawy',
        'resubmitted' => 'poprawił(a) i złożył(a) ponownie',
        'cancelled' => 'wycofał(a) wniosek',
    ],

    'task_status' => [
        'todo' => 'Do zrobienia',
        'in_progress' => 'W trakcie',
        'done' => 'Gotowe',
        'cancelled' => 'Anulowane',
    ],

    'task_priority' => [
        1 => 'Niski',
        2 => 'Zwykły',
        3 => 'Wysoki',
        4 => 'Pilny',
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

    'event_category' => [
        'open_day' => 'Dzień otwarty',
        'competition' => 'Konkurs / zawody',
        'recruitment' => 'Rekrutacja',
        'ceremony' => 'Uroczystość',
        'sport' => 'Sport',
        'charity' => 'Akcja charytatywna',
        'meeting' => 'Spotkanie',
        'other' => 'Inne',
    ],

    'offer_kind' => [
        'technikum' => 'Technikum',
        'branzowa' => 'Szkoła branżowa',
        'liceum' => 'Liceum',
        'policealna' => 'Szkoła policealna',
        'kurs' => 'Kurs / KKZ',
        'inne' => 'Inne',
    ],

    'project_status' => [
        'planned' => 'Planowany',
        'in_progress' => 'W toku',
        'on_hold' => 'Wstrzymany',
        'completed' => 'Zakończony',
        'cancelled' => 'Anulowany',
    ],
    'project_kind' => [
        'event' => 'Wydarzenie szkolne',
        'open_day' => 'Dzień otwarty',
        'competition' => 'Konkurs',
        'grant' => 'Grant',
        'erasmus' => 'Erasmus+',
        'investment' => 'Inwestycja',
        'other' => 'Inny',
    ],
];
