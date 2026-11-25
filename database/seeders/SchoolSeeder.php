<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

/**
 * Creates the first school from .env (MCI_SCHOOL_*) if no school exists.
 */
class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (School::withTrashed()->exists()) {
            return;
        }

        $domains = array_values(array_filter(array_map('trim', explode(',', (string) config('mci.school.email_domains')))));

        School::query()->create([
            'name' => config('mci.school.name'),
            'short_name' => config('mci.school.short_name'),
            'slug' => config('mci.school.slug') ?: null,
            'email_domains' => $domains ?: null,
            'settings' => ['allow_self_registration' => true, 'max_profile_visibility' => 'network'],
        ]);
    }
}
