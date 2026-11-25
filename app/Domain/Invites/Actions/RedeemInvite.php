<?php

namespace App\Domain\Invites\Actions;

use App\Enums\GroupRole;
use App\Enums\InviteKind;
use App\Enums\SchoolMemberStatus;
use App\Models\Group;
use App\Models\Invite;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Applies an invite to a user: school membership, groups (member/leader)
 * and an optional school role — all in one transaction, with the invite row
 * locked so max_uses can never be exceeded by parallel scans.
 *
 * Existing access is never reduced: a leader stays a leader, roles are only
 * added. Redeeming the same invite twice is harmless and not counted again.
 */
class RedeemInvite
{
    /**
     * @return array{already: bool, groups: list<string>}
     */
    public function handle(Invite $invite, User $user, ?string $ip = null, ?string $userAgent = null, bool $createdAccount = false): array
    {
        return DB::transaction(function () use ($invite, $user, $ip, $userAgent, $createdAccount) {
            /** @var Invite $locked */
            $locked = Invite::query()->whereKey($invite->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('school', $invite->school);

            $already = $locked->redemptions()->where('user_id', $user->id)->exists();

            if (! $already && ! $locked->isUsable()) {
                throw ValidationException::withMessages(['invite' => __('Zaproszenie jest nieważne lub wygasło.')]);
            }

            if ($locked->kind === InviteKind::Personal && mb_strtolower($user->email) !== $locked->email) {
                throw ValidationException::withMessages(['invite' => __('To zaproszenie jest przypisane do innego adresu e-mail.')]);
            }

            $school = $locked->school;
            $membership = $user->schools()->whereKey($school->id)->first()?->pivot;

            if ($membership && $membership->status === SchoolMemberStatus::Suspended->value) {
                throw ValidationException::withMessages(['invite' => __('Twój dostęp do tej szkoły jest zawieszony. Skontaktuj się z administratorem szkoły.')]);
            }

            if (! $membership) {
                $user->schools()->attach($school->id, [
                    'status' => SchoolMemberStatus::Active->value,
                    'joined_via' => 'invite',
                    'invite_id' => $locked->id,
                ]);
            }

            $joined = [];
            foreach ($invite->groups as $group) {
                /** @var Group $group */
                $role = $invite->groupRole($group);
                $current = $user->groups()->whereKey($group->id)->first()?->pivot;

                if (! $current) {
                    $user->groups()->attach($group->id, ['role' => $role->value, 'joined_via' => 'invite', 'invite_id' => $locked->id]);
                    $joined[] = $group->name;
                } elseif ($role === GroupRole::Leader && $current->role !== GroupRole::Leader) {
                    $user->groups()->updateExistingPivot($group->id, ['role' => GroupRole::Leader->value]);
                    $joined[] = $group->name;
                }
            }
            $user->flushLeadershipCache();

            if ($locked->role_name) {
                $registrar = app(PermissionRegistrar::class);
                $previous = $registrar->getPermissionsTeamId();
                $registrar->setPermissionsTeamId($school->id);
                $user->unsetRelation('roles');
                if (! $user->hasRole($locked->role_name)) {
                    $user->assignRole($locked->role_name);
                }
                $registrar->setPermissionsTeamId($previous);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }

            if (! $already) {
                $locked->redemptions()->create([
                    'user_id' => $user->id,
                    'created_account' => $createdAccount,
                    'ip' => $ip,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                    'redeemed_at' => now(),
                ]);
                $locked->increment('uses_count');

                Audit::log('invite.redeemed', $locked, [
                    'user_id' => $user->id,
                    'groups' => $invite->groups->pluck('id')->all(),
                    'role' => $locked->role_name,
                    'new_school_member' => $membership === null,
                ], $user, $school->id);
            }

            return ['already' => $already && $joined === [], 'groups' => $joined];
        });
    }
}
