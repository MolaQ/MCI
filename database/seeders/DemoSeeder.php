<?php

namespace Database\Seeders;

use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\Role;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local/demo data only: ~70 staff spread over realistic groups.
 * Run with: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder is disabled in production.');

            return;
        }

        $subjects = ['Matematyka', 'Język polski', 'Języki obce', 'Mechatronika', 'Mechanika i budowa maszyn', 'Informatyka', 'Wychowanie fizyczne'];
        $subjectGroups = collect($subjects)->map(fn ($name) => Group::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'type' => in_array($name, ['Mechatronika', 'Mechanika i budowa maszyn'], true) ? GroupType::Vocational : GroupType::Subject],
        ));

        $all = Group::query()->where('slug', 'wszyscy-pracownicy')->first();
        $teachers = Group::query()->where('slug', 'nauczyciele')->first();
        $tutors = Group::query()->where('slug', 'wychowawcy')->first();
        $office = Group::query()->where('slug', 'administracja-i-obsluga')->first();
        $volunteering = Group::query()->where('slug', 'wolontariat')->first();

        $coordinators = User::factory(3)->withRole(Role::Coordinator->value)->create();
        $teacherUsers = User::factory(55)->withRole(Role::Teacher->value)->create();
        $staffUsers = User::factory(10)->withRole(Role::Staff->value)->create();
        User::factory(2)->invited()->withRole(Role::Teacher->value)->create();

        foreach ($coordinators->concat($teacherUsers)->concat($staffUsers) as $user) {
            $all?->members()->syncWithoutDetaching([$user->id => ['role' => GroupRole::Member->value]]);
        }

        foreach ($coordinators->concat($teacherUsers) as $i => $user) {
            $teachers?->members()->syncWithoutDetaching([$user->id => ['role' => GroupRole::Member->value]]);
            $subjectGroups[$i % $subjectGroups->count()]->members()->syncWithoutDetaching([
                $user->id => ['role' => $i < $subjectGroups->count() ? GroupRole::Leader->value : GroupRole::Member->value],
            ]);
            if ($i % 2 === 0) {
                $tutors?->members()->syncWithoutDetaching([$user->id => ['role' => GroupRole::Member->value]]);
            }
            if ($i % 7 === 0) {
                $volunteering?->members()->syncWithoutDetaching([$user->id => ['role' => $i === 0 ? GroupRole::Leader->value : GroupRole::Member->value]]);
            }
        }

        foreach ($staffUsers as $user) {
            $office?->members()->syncWithoutDetaching([$user->id => ['role' => GroupRole::Member->value]]);
        }

        // The school safety officer: an ordinary teacher with an individual alerts.send grant.
        $teacherUsers->first()->givePermissionTo('alerts.send');

        $this->command?->info('Demo data: 70 staff accounts in '.Group::query()->count().' groups.');
    }
}
