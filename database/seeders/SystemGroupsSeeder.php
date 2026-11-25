<?php

namespace Database\Seeders;

use App\Enums\GroupType;
use App\Enums\MembershipMode;
use App\Models\Group;
use Illuminate\Database\Seeder;

/**
 * Starter groups for a typical Polish vocational school. Idempotent by slug;
 * everything except the system group can be renamed or deleted later.
 */
class SystemGroupsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['slug' => 'wszyscy-pracownicy', 'name' => 'Wszyscy pracownicy', 'type' => GroupType::Staff, 'is_system' => true],
            ['slug' => 'nauczyciele', 'name' => 'Nauczyciele', 'type' => GroupType::Staff],
            ['slug' => 'administracja-i-obsluga', 'name' => 'Administracja i obsługa', 'type' => GroupType::Staff],
            ['slug' => 'dyrekcja', 'name' => 'Dyrekcja', 'type' => GroupType::Staff],
            ['slug' => 'wychowawcy', 'name' => 'Wychowawcy', 'type' => GroupType::ClassTutors],
            ['slug' => 'nauczyciele-przedmiotow-zawodowych', 'name' => 'Nauczyciele przedmiotów zawodowych', 'type' => GroupType::Vocational, 'parent' => 'nauczyciele'],
            ['slug' => 'nauczyciele-przedmiotow-ogolnych', 'name' => 'Nauczyciele przedmiotów ogólnych', 'type' => GroupType::Subject, 'parent' => 'nauczyciele'],
            ['slug' => 'wolontariat', 'name' => 'Wolontariat', 'type' => GroupType::Volunteering, 'mode' => MembershipMode::Open],
            ['slug' => 'sport', 'name' => 'Sport', 'type' => GroupType::Sport, 'mode' => MembershipMode::Open],
        ];

        foreach ($groups as $g) {
            Group::withTrashed()->updateOrCreate(['slug' => $g['slug']], [
                'name' => $g['name'],
                'type' => $g['type'],
                'is_system' => $g['is_system'] ?? false,
                'membership_mode' => $g['mode'] ?? MembershipMode::Managed,
                'parent_id' => isset($g['parent']) ? Group::query()->where('slug', $g['parent'])->value('id') : null,
            ]);
        }
    }
}
