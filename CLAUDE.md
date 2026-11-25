# MCI — notes for AI assistants

Read `docs/ARCHITECTURE.md` first: it is the source of truth for the data
model, permission model and roadmap. `README.md` has setup commands.

- Stack: Laravel 13, Livewire 4 (class-based components in `app/Livewire`,
  views in `resources/views/livewire`), Flux UI free, Tailwind v4, Pest.
- Must keep working on shared PHP hosting: no long-running processes are
  required; queues are drained by the scheduler (`routes/console.php`), and
  real-time views must fall back to `wire:poll` (`config('mci.realtime')`).
- UI language is Polish; write user-facing strings as `__('Polski tekst')`.
- Permissions are enum-backed (`App\Enums\Permission`, `App\Enums\Role`).
  Add new permissions to the enum; `RolesAndPermissionsSeeder` is idempotent.
- Put business logic in `app/Domain/*/Actions`, log sensitive changes with
  `App\Support\Audit::log()`, and authorize in policies.
- Theme colours come from CSS variables injected by `App\Support\Appearance`;
  use `bg-primary`, `text-primary-foreground`, `rounded-mci`, Flux `accent`.
- Run `php artisan test` and `vendor/bin/pint` before committing.
