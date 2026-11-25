<?php

namespace Database\Seeders;

use App\Enums\GroupRole;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * The first administrator: site admin of the portal and school admin of
 * the first school.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('mci.admin.email');

        if (! $email) {
            $this->command?->warn('MCI_ADMIN_EMAIL is not set — skipping the first administrator.');

            return;
        }

        $school = School::query()->orderBy('id')->first();
        $user = User::withTrashed()->firstWhere('email', mb_strtolower($email));
        // Bootstrap only: an account that already exists (even deleted or
        // demoted on purpose) is left exactly as the administrators set it.
        if ($user) {
            $this->command?->info("Account {$email} already exists — left unchanged.");

            return;
        }

        $password = config('mci.admin.password') ?: Str::password(16, symbols: false);
        $user = User::query()->create([
            'first_name' => config('mci.admin.first_name'),
            'last_name' => config('mci.admin.last_name'),
            'email' => mb_strtolower($email),
            'password' => $password,
            'status' => UserStatus::Active,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->forceFill(['is_site_admin' => true])->save();

        if ($school) {
            $user->schools()->syncWithoutDetaching([$school->id => ['status' => 'active', 'joined_via' => 'admin']]);

            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($school->id);
            $user->unsetRelation('roles')->assignRole(Role::Admin->value);
            $registrar->setPermissionsTeamId(null);

            if ($staff = Group::query()->where('school_id', $school->id)->where('slug', 'wszyscy-pracownicy')->first()) {
                $staff->members()->syncWithoutDetaching([$user->id => ['role' => GroupRole::Member->value, 'joined_via' => 'admin']]);
            }
        }

        $this->command?->info("Administrator ready: {$email}");
        if ($password && ! config('mci.admin.password')) {
            $this->command?->warn("Generated password: {$password}  (change it after first login)");
        }
    }
}
