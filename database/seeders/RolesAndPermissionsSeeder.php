<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deploy. Roles are global definitions
 * (school_id = null) that are *assigned* per school. Role → permission
 * defaults are only applied to roles created now (admin is always kept
 * complete), so later changes made in the app are preserved.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public static bool $resetDefaults = false;

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            $model = RoleModel::query()->where(['name' => $role->value, 'guard_name' => 'web'])->whereNull('school_id')->first();
            $isNew = $model === null;
            $model ??= RoleModel::query()->create(['name' => $role->value, 'guard_name' => 'web', 'school_id' => null]);

            if ($isNew || static::$resetDefaults || $role === Role::Admin) {
                $model->syncPermissions(array_map(fn (Permission $p) => $p->value, $role->defaultPermissions()));
            }
        }

        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId($previousTeam);
    }
}
