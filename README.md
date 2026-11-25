# MCI — Mechaniczne Centrum Informacyjne

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
| 5–11 | To-Do, Workflow, Project Manager, safety alerts, TV mode, operations (ZIP updates, daily 03:00 backups kept 14 days), logs & statistics | planned |

## Local development

Requirements: PHP 8.3+, Composer, Node 20+, MySQL 8 / MariaDB 10.6+ (or SQLite for a quick start).

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

Quick start without MySQL: set `DB_CONNECTION=sqlite`, remove the other `DB_*`
lines and run `touch database/database.sqlite` before migrating.

## Tests

```bash
php artisan test              # Pest, SQLite in memory
vendor/bin/pint               # code style
```

CI runs the suite on SQLite and on MySQL 8 (`.github/workflows/tests.yml`).

## Conventions

- Code, database and identifiers in English; every UI string goes through
  `__()` with Polish source text, plus `lang/pl/*.php` for enums and messages.
- Business logic lives in `app/Domain/*/Actions`; Livewire components stay thin.
- Authorization: Spatie roles/permissions (`app/Enums/Permission.php`) plus
  scoped rules in policies (group leaders, later event leads).
- Every change to accounts, access, groups or settings is written to `audit_logs`.
