# SOWA — Szkolny Ośrodek Wiadomości i Alarmowania

_Repository/code name: MCI. The displayed name and subtitle are set in Wygląd (appearance settings)._

School information hub: targeted announcements, safety alerts with mandatory
acknowledgment ("Potwierdzam odbiór"), and multi-stage events / tasks — built
with Laravel 13, Livewire 4, Flux UI, Tailwind CSS v4 and MySQL. Runs on
ordinary shared PHP hosting (cron only); a VPS is optional.

- Design, data model and roadmap: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- Installing on shared hosting or a VPS: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)

## Status

| Step | Scope | State |
|---|---|---|
| 0 | Foundations (Laravel, Livewire, Flux, Polish UI, CI) | ✅ |
| 1 | Users, roles & permissions, groups, CSV import, appearance settings, audit log | ✅ |
| 2 | Multi-school portal (school switcher, school logs vs site journal, profile privacy), invites & QR onboarding with multi-group joining | ✅ |
| 3 | Public portal front (schools, offers, public events, TV board, iCal) and personalised communication centre (targeted announcements, `@` mentions, read/confirm, bell) | ✅ |
| 4 | Installable PWA (offline page) + Web Push to devices, image editor & media, site/school feature switches | ✅ |
| 5 | To-Do (personal and assigned tasks, checklists, reminders) | ✅ |
| 6 | Workflow (request templates, multi-stage approvals, SLA reminders) | ✅ |
| 7 | Project Manager (milestones, linked tasks and requests), grouped sidebar, favicon editor, safe demo mode | ✅ |
| 8–11 | Safety alerts, TV mode, operations (ZIP updates, daily 03:00 backups kept 14 days), logs & statistics | planned |

## Local development

Requirements: PHP 8.3+, Composer, Node 20+, MySQL 8 / MariaDB 10.6+.

```bash
composer install
cp .env.example .env          # set DB_* and MCI_ADMIN_EMAIL / MCI_ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed    # roles, permissions, starter groups, first admin
php artisan db:seed --class=DemoSeeder   # optional: ~70 demo staff accounts
npm install && npm run build
php artisan storage:link
php artisan serve
```

Demo mode uses a separate database (`DB_DEMO_DATABASE`, default `<DB_DATABASE>_demo`),
never the production one. Create it empty with the same user, then switch it in
*Witryna → Tryb demo* or with `php artisan mci:demo status|reset|on|off`
(see `docs/ARCHITECTURE.md`, "Demo mode").

## Tests

Tests run on MySQL / MariaDB only, in a separate database called `mci_test`
(the suite empties it on every run and refuses any database whose name does not
contain "test"). Host, port, user and password come from your `.env`.

```sql
CREATE DATABASE mci_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON mci_test.* TO 'mci'@'localhost';   -- or the user from your .env
```

```bash
php artisan test              # Pest on MySQL/MariaDB (database mci_test)
vendor/bin/pint               # code style
```

CI runs the same suite on MySQL 8 (`.github/workflows/tests.yml`).

## Conventions

- Code, database and identifiers in English; every UI string goes through
  `__()` with Polish source text, plus `lang/pl/*.php` for enums and messages.
- Business logic lives in `app/Domain/*/Actions`; Livewire components stay thin.
- Authorization: Spatie roles/permissions (`app/Enums/Permission.php`) plus
  scoped rules in policies (group leaders, later event leads).
- Every change to accounts, access, groups or settings is written to `audit_logs`.
