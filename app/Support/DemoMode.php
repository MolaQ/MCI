<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Demo / test mode without touching production data.
 *
 * - Demo data lives in its own database, reached through the "demo"
 *   connection (a copy of the main connection with another database name:
 *   DB_DEMO_DATABASE, or "<main>_demo"; for SQLite database/demo.sqlite).
 * - The switch is a small flag file in storage/ — outside both databases —
 *   read at boot (AppServiceProvider::register). While it is on, every
 *   request, queue job and scheduled command uses the demo connection, a
 *   separate media folder, cache prefix and session cookie.
 * - Guardrails: the demo database must differ from the main one and its
 *   name must contain "demo"; resetting only ever runs migrate:fresh and
 *   the seeders with --database=demo; on/off/reset are written to the
 *   site journal of the MAIN database, whichever mode is active.
 */
class DemoMode
{
    public const CONNECTION = 'demo';

    /** Defines the "demo" connection and, if the flag is set, switches the app to it. */
    public static function boot(): void
    {
        $main = (string) config('mci.demo.main_connection');
        $base = (array) config("database.connections.{$main}", []);
        config(['database.connections.'.self::CONNECTION => array_merge($base, ['database' => self::databaseName()])]);

        if (! self::isOn()) {
            return;
        }

        config([
            'database.default' => self::CONNECTION,
            'cache.prefix' => config('cache.prefix').'demo_',
            'session.cookie' => config('session.cookie').'_demo',
            'filesystems.disks.media.root' => storage_path('app/demo/media'),
        ]);
    }

    public static function isOn(): bool
    {
        return is_file((string) config('mci.demo.flag'));
    }

    /** @return array{enabled_at?: string, by?: string}|null */
    public static function state(): ?array
    {
        return self::isOn() ? (json_decode((string) File::get(config('mci.demo.flag')), true) ?: []) : null;
    }

    public static function databaseName(): string
    {
        $main = (string) config('mci.demo.main_connection');
        $connection = (array) config("database.connections.{$main}", []);

        if ($configured = config('mci.demo.database')) {
            return (string) $configured;
        }

        return ($connection['driver'] ?? null) === 'sqlite'
            ? database_path('demo.sqlite')
            : ($connection['database'] ?? 'mci').'_demo';
    }

    public static function mainDatabaseName(): string
    {
        return (string) config('database.connections.'.config('mci.demo.main_connection').'.database');
    }

    /** Refuses anything that could point demo operations at production data. */
    public static function assertSafe(): void
    {
        $demo = self::databaseName();
        $main = self::mainDatabaseName();

        if ($demo === '' || $demo === $main || realpath($demo) && realpath($demo) === realpath($main)) {
            throw new RuntimeException(__('Baza demo musi być inna niż baza główna.'));
        }
        if (! str_contains(mb_strtolower(basename($demo)), 'demo')) {
            throw new RuntimeException(__('Nazwa bazy demo musi zawierać słowo „demo” (teraz: :name).', ['name' => $demo]));
        }
        if (config('mci.demo.main_connection') === self::CONNECTION) {
            throw new RuntimeException(__('Połączenie główne nie może nazywać się „demo”.'));
        }
    }

    /** Is the demo database reachable and already set up? */
    public static function isReady(): bool
    {
        try {
            return Schema::connection(self::CONNECTION)->hasTable('users')
                && DB::connection(self::CONNECTION)->table('users')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Rebuilds the demo database from scratch: schema + base data + demo data. */
    public static function reset(?User $actor = null): void
    {
        self::assertSafe();

        $config = (array) config('database.connections.'.self::CONNECTION);
        if (($config['driver'] ?? null) === 'sqlite' && ! is_file($config['database'])) {
            File::put($config['database'], '');
        }
        DB::purge(self::CONNECTION);

        try {
            self::ensureDatabase($config);
            DB::connection(self::CONNECTION)->getPdo();
        } catch (\Throwable $e) {
            throw new RuntimeException(__('Nie można połączyć się z bazą demo „:name”. Utwórz ją w panelu hostingu (z tymi samymi danymi logowania co baza główna) albo ustaw DB_DEMO_DATABASE.', ['name' => self::databaseName()]), 0, $e);
        }

        $default = config('database.default');
        try {
            Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
            Artisan::call('db:seed', ['--database' => self::CONNECTION, '--class' => 'Database\\Seeders\\DemoDatabaseSeeder', '--force' => true]);
        } finally {
            config(['database.default' => $default]);
            DB::setDefaultConnection($default);
        }
        File::deleteDirectory(storage_path('app/demo/media'));

        self::journal('demo.reset', $actor);
    }

    /**
     * MySQL / MariaDB: creates the demo database when it is missing and the
     * main database user may do so (XAMPP root, a VPS). On shared hosting
     * without that right it fails and the caller explains what to do.
     * The name has passed assertSafe() (differs from main, contains "demo").
     */
    private static function ensureDatabase(array $config): void
    {
        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            return;
        }
        try {
            DB::connection(self::CONNECTION)->getPdo();

            return;
        } catch (\Throwable) {
            DB::purge(self::CONNECTION);
        }

        $name = str_replace('`', '', (string) $config['database']);
        $charset = preg_replace('/[^a-z0-9_]/i', '', (string) ($config['charset'] ?? 'utf8mb4'));
        $collation = preg_replace('/[^a-z0-9_]/i', '', (string) ($config['collation'] ?? 'utf8mb4_unicode_ci'));
        DB::connection((string) config('mci.demo.main_connection'))
            ->statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    public static function enable(?User $actor = null): void
    {
        self::assertSafe();
        if (! self::isReady()) {
            self::reset($actor);
        }
        File::ensureDirectoryExists(dirname(config('mci.demo.flag')));
        File::put(config('mci.demo.flag'), json_encode(['enabled_at' => now()->toIso8601String(), 'by' => $actor?->email ?? 'console'], JSON_PRETTY_PRINT));

        self::journal('demo.enabled', $actor);
    }

    public static function disable(?User $actor = null): void
    {
        File::delete(config('mci.demo.flag'));

        self::journal('demo.disabled', $actor);
    }

    /** Site journal of the MAIN database (production keeps the record). */
    private static function journal(string $action, ?User $actor): void
    {
        $main = (string) config('mci.demo.main_connection');
        $onMain = DB::connection()->getName() === $main;

        AuditLog::on($main)->create([
            'school_id' => null,
            // Ids of demo accounts mean nothing in the main database.
            'actor_id' => $onMain ? $actor?->getKey() : null,
            'action' => $action,
            'properties' => array_filter(['database' => self::databaseName(), 'by' => $actor?->email]),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
