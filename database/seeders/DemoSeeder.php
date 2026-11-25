<?php

namespace Database\Seeders;

use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\Role;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local/demo data only: ~70 staff in the first school, plus a small
 * partner school that shares a few teachers (multi-school demo).
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

        $current = app(CurrentSchool::class);
        $school = School::query()->orderBy('id')->firstOrFail();
        $current->set($school);

        $subjects = ['Matematyka', 'Język polski', 'Języki obce', 'Mechatronika', 'Mechanika i budowa maszyn', 'Informatyka', 'Wychowanie fizyczne'];
        $subjectGroups = collect($subjects)->map(fn ($name) => Group::query()->firstOrCreate(
            ['school_id' => $school->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'type' => in_array($name, ['Mechatronika', 'Mechanika i budowa maszyn'], true) ? GroupType::Vocational : GroupType::Subject],
        ));

        $group = fn (string $slug) => Group::query()->where('school_id', $school->id)->where('slug', $slug)->first();
        $all = $group('wszyscy-pracownicy');
        $teachers = $group('nauczyciele');
        $tutors = $group('wychowawcy');
        $office = $group('administracja-i-obsluga');
        $volunteering = $group('wolontariat');

        $make = function (int $count, Role $role, bool $invited = false) use ($school) {
            $factory = User::factory($count);
            $factory = $invited ? $factory->invited() : $factory;

            return $factory->create()->each(function (User $u) use ($school, $role) {
                $u->schools()->attach($school->id, ['status' => 'active', 'joined_via' => 'import']);
                $u->assignRole($role->value);
            });
        };

        $coordinators = $make(3, Role::Coordinator);
        $teacherUsers = $make(55, Role::Teacher);
        $staffUsers = $make(10, Role::Staff);
        $make(2, Role::Teacher, invited: true);

        $attach = fn (?Group $g, User $u, GroupRole $r = GroupRole::Member) => $g?->members()->syncWithoutDetaching([$u->id => ['role' => $r->value]]);

        foreach ($coordinators->concat($teacherUsers)->concat($staffUsers) as $user) {
            $attach($all, $user);
        }

        foreach ($coordinators->concat($teacherUsers)->values() as $i => $user) {
            $attach($teachers, $user);
            $attach($subjectGroups[$i % $subjectGroups->count()], $user, $i < $subjectGroups->count() ? GroupRole::Leader : GroupRole::Member);
            if ($i % 2 === 0) {
                $attach($tutors, $user);
            }
            if ($i % 7 === 0) {
                $attach($volunteering, $user, $i === 0 ? GroupRole::Leader : GroupRole::Member);
            }
        }

        foreach ($staffUsers as $user) {
            $attach($office, $user);
        }

        // The school safety officer: an ordinary teacher with an individual alerts.send grant.
        $teacherUsers->first()->givePermissionTo('alerts.send');

        // A partner school sharing three teachers.
        $partner = School::query()->firstOrCreate(['slug' => 'zs2'], [
            'name' => 'Zespół Szkół nr 2', 'short_name' => 'ZS2',
            'settings' => ['allow_self_registration' => true, 'max_profile_visibility' => 'school'],
        ]);
        SystemGroupsSeeder::seedFor($partner);
        $current->set($partner);
        foreach ($teacherUsers->take(3) as $user) {
            $user->schools()->syncWithoutDetaching([$partner->id => ['status' => 'active', 'joined_via' => 'admin']]);
            $user->unsetRelation('roles')->assignRole(Role::Teacher->value);
        }
        $current->set(null);

        $this->command?->info('Demo data: 70 staff in '.$school->short_name.', partner school '.$partner->short_name.' with 3 shared teachers.');
    }
}
