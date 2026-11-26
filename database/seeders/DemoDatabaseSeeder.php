<?php

namespace Database\Seeders;

use App\Support\DemoMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fills the separate demo database (Witryna → Tryb demo). Refuses to run
 * on any connection but "demo". The demo administrator gets its own
 * account (MCI_DEMO_ADMIN_EMAIL / MCI_DEMO_ADMIN_PASSWORD), never the
 * production one.
 */
class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::getDefaultConnection() !== DemoMode::CONNECTION) {
            throw new RuntimeException('DemoDatabaseSeeder runs only on the "demo" connection: php artisan mci:demo reset');
        }
        DemoMode::assertSafe();

        config([
            'mci.admin.email' => config('mci.demo.admin_email'),
            'mci.admin.password' => config('mci.demo.admin_password'),
            'mci.admin.first_name' => 'Demo',
            'mci.admin.last_name' => 'Administrator',
        ]);

        $this->call([DatabaseSeeder::class, DemoSeeder::class]);
    }
}
