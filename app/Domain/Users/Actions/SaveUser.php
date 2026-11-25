<?php

namespace App\Domain\Users\Actions;

use App\Domain\Groups\Actions\SyncUserGroups;
use App\Enums\SchoolMemberStatus;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Audit;
use App\Support\CurrentSchool;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a user account from the admin form / CSV import, in
 * the context of the active school: the account is added to that school,
 * roles / permissions / groups are changed only within that school.
 *
 * $data keys: first_name, last_name, email, phone, status, roles (list of
 * role names), permissions (direct permission names, optional),
 * groups (group_id => role, optional).
 */
class SaveUser
{
    public function __construct(private SyncUserGroups $syncGroups, private CurrentSchool $current) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($data, $user) {
            $creating = $user === null;
            $user ??= new User(['created_by' => auth()->id()]);

            $before = $creating ? [] : $user->only(['first_name', 'last_name', 'email', 'phone', 'status']);

            $user->fill(Arr::only($data, ['first_name', 'last_name', 'email', 'phone']));
            $user->email = mb_strtolower(trim($user->email));

            if (isset($data['status'])) {
                $user->status = $data['status'] instanceof UserStatus ? $data['status'] : UserStatus::from($data['status']);
            } elseif ($creating) {
                $user->status = UserStatus::Invited;
            }

            $user->save();

            if ($school = $this->current->get()) {
                if (! $user->schools()->whereKey($school->id)->exists()) {
                    $user->schools()->attach($school->id, [
                        'status' => SchoolMemberStatus::Active->value,
                        'joined_via' => $data['joined_via'] ?? 'admin',
                    ]);
                    if (! $creating) {
                        Audit::log('school.member_added', $user);
                    }
                }
            }

            if (array_key_exists('roles', $data)) {
                $oldRoles = $creating ? [] : $user->getRoleNames()->all();
                $user->syncRoles($data['roles']);
                $newRoles = $user->getRoleNames()->all();
                if (array_diff($oldRoles, $newRoles) || array_diff($newRoles, $oldRoles)) {
                    Audit::log('user.roles_changed', $user, ['from' => $oldRoles, 'to' => $newRoles]);
                }
            }

            if (array_key_exists('permissions', $data)) {
                $old = $creating ? [] : $user->getDirectPermissions()->pluck('name')->all();
                $user->syncPermissions($data['permissions']);
                $new = $user->getDirectPermissions()->pluck('name')->all();
                if (array_diff($old, $new) || array_diff($new, $old)) {
                    Audit::log('user.permissions_changed', $user, ['from' => $old, 'to' => $new]);
                }
            }

            if (array_key_exists('groups', $data)) {
                $this->syncGroups->handle($user, $data['groups']);
            }

            if ($creating) {
                Audit::log('user.created', $user, ['email' => $user->email]);
            } else {
                $after = $user->only(array_keys($before));
                $diff = array_diff_assoc(array_map(fn ($v) => $v instanceof UserStatus ? $v->value : $v, $after), array_map(fn ($v) => $v instanceof UserStatus ? $v->value : $v, $before));
                if ($diff) {
                    Audit::log('user.updated', $user, ['changed' => array_keys($diff)]);
                }
            }

            return $user;
        });
    }
}
