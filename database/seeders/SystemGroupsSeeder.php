<?php

namespace Database\Seeders;

use App\Enums\MembershipMode;
use App\Enums\TermKind;
use App\Models\Group;
use App\Models\School;
use App\Models\Term;
use Illuminate\Database\Seeder;

/**
 * Starter groups for every school (typical Polish vocational school).
 * Creates missing (school, slug) pairs only; everything except the system
 * group can be renamed or deleted later.
 */
class SystemGroupsSeeder extends Seeder
{
    public function run(): void
    {
        School::query()->each(fn (School $school) => static::seedFor($school));
    }

    public static function seedFor(School $school): void
    {
        $groups = [
            ['slug' => 'wszyscy-pracownicy', 'name' => 'Wszyscy pracownicy', 'type' => 'staff', 'is_system' => true],
            ['slug' => 'nauczyciele', 'name' => 'Nauczyciele', 'type' => 'staff'],
            ['slug' => 'administracja-i-obsluga', 'name' => 'Administracja i obsługa', 'type' => 'staff'],
            ['slug' => 'dyrekcja', 'name' => 'Dyrekcja', 'type' => 'staff'],
            ['slug' => 'wychowawcy', 'name' => 'Wychowawcy', 'type' => 'class_tutors'],
            ['slug' => 'nauczyciele-przedmiotow-zawodowych', 'name' => 'Nauczyciele przedmiotów zawodowych', 'type' => 'vocational', 'parent' => 'nauczyciele'],
            ['slug' => 'nauczyciele-przedmiotow-ogolnych', 'name' => 'Nauczyciele przedmiotów ogólnych', 'type' => 'subject', 'parent' => 'nauczyciele'],
            ['slug' => 'wolontariat', 'name' => 'Wolontariat', 'type' => 'volunteering', 'mode' => MembershipMode::Open],
            ['slug' => 'sport', 'name' => 'Sport', 'type' => 'sport', 'mode' => MembershipMode::Open],
        ];

        foreach ($groups as $g) {
            // Create missing groups only; never overwrite what admins changed
            // (or bring back a group they deleted).
            Group::withTrashed()->firstOrCreate(['school_id' => $school->id, 'slug' => $g['slug']], [
                'name' => $g['name'],
                'type_id' => Term::idFor(TermKind::GroupType, $g['type']),
                'is_system' => $g['is_system'] ?? false,
                'membership_mode' => $g['mode'] ?? MembershipMode::Managed,
                'parent_id' => isset($g['parent']) ? Group::query()->where('school_id', $school->id)->where('slug', $g['parent'])->value('id') : null,
            ]);
        }
    }
}
