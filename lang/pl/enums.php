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
        'tasks_assign_any' => 'Przydzielanie zadań dowolnym osobom',
        'stats_view' => 'Podgląd statystyk',
        'audit_view' => 'Podgląd dziennika zdarzeń',
        'settings_manage' => 'Ustawienia systemu i wygląd',
    ],
];
