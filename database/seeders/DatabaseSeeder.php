<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe and idempotent: run after every deploy with
     *   php artisan db:seed --force
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SchoolSeeder::class,
            SystemGroupsSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
