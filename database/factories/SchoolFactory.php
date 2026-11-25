<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        $short = 'ZS'.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => 'Zespół Szkół nr '.Str::after($short, 'ZS'),
            'short_name' => $short,
            'slug' => Str::slug($short),
            'email_domains' => null,
            'settings' => ['allow_self_registration' => true, 'max_profile_visibility' => 'network'],
            'is_active' => true,
        ];
    }
}
