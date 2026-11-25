<?php

namespace Database\Seeders;

use App\Enums\GroupRole;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('mci.admin.email');

        if (! $email) {
            $this->command?->warn('MCI_ADMIN_EMAIL is not set — skipping the first administrator.');

            return;
        }

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
        $user->assignRole(Role::Admin->value);

        if ($staff = Group::query()->where('slug', 'wszyscy-pracownicy')->first()) {
            $staff->members()->syncWithoutDetaching([$user->id => ['role' => GroupRole::Member->value, 'joined_via' => 'admin']]);
        }

        $this->command?->info("Administrator created: {$email}");
        if (! config('mci.admin.password')) {
            $this->command?->warn("Generated password: {$password}  (change it after first login)");
        }
    }
}
