<?php

namespace App\Domain\Schools\Actions;

use App\Enums\SchoolMemberStatus;
use App\Models\School;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * School-level account actions. A school can suspend or remove a person
 * from *itself*; the account and its other schools are unaffected.
 */
class ManageSchoolMembership
{
    public function setStatus(User $user, School $school, SchoolMemberStatus $status): void
    {
        $updated = $user->schools()->updateExistingPivot($school->id, ['status' => $status->value]);

        if ($updated) {
            Audit::log($status === SchoolMemberStatus::Suspended ? 'school.member_suspended' : 'school.member_restored', $user, schoolId: $school->id);
        }
    }

    public function remove(User $user, School $school): void
    {
        DB::transaction(function () use ($user, $school) {
            $groupIds = $user->groups()->where('groups.school_id', $school->id)->pluck('groups.id');
            $user->groups()->detach($groupIds);

            $registrar = app(PermissionRegistrar::class);
            $previous = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($school->id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');

            $user->schools()->detach($school->id);

            Audit::log('school.member_removed', $user, ['groups' => $groupIds->all()], schoolId: $school->id);
        });
    }
}
