<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deploy. Creates missing permissions and
 * roles. Role → permission defaults are only applied to roles created now,
 * so changes made later by an admin are preserved (pass --force-defaults
 * via RolesAndPermissionsSeeder::$resetDefaults to override).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public static bool $resetDefaults = false;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            $model = RoleModel::query()->where(['name' => $role->value, 'guard_name' => 'web'])->first();
            $isNew = $model === null;
            $model ??= RoleModel::create(['name' => $role->value, 'guard_name' => 'web']);

            if ($isNew || static::$resetDefaults || $role === Role::Admin) {
                $model->syncPermissions(array_map(fn (Permission $p) => $p->value, $role->defaultPermissions()));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
