<?php

namespace App\Domain\Invites\Actions;

use App\Enums\GroupRole;
use App\Enums\InviteKind;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Invite;
use App\Models\School;
use App\Models\User;
use App\Notifications\InviteNotification;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Creates an invite. Security rules (docs/ARCHITECTURE.md §5.1):
 *  - every group must belong to the invite's school and the creator must be
 *    allowed to invite to it (invites.create, or leader of that group);
 *  - leader seats can only be handed out by someone who may manage leaders;
 *  - a school role can only be attached with roles.manage, and the admin
 *    role only on personal (e-mail bound, single-use) invites;
 *  - expiry is capped by config('mci.invites.max_days').
 */
class CreateInvite
{
    /**
     * @param  array{kind: InviteKind|string, label?: ?string, email?: ?string, role_name?: ?string,
     *               groups: array<int|string, string>, max_uses?: ?int, expires_at?: mixed,
     *               allow_self_registration?: bool, send_email?: bool}  $data
     * @return array{0: Invite, 1: string} the invite and its plain token
     */
    public function handle(User $creator, School $school, array $data): array
    {
        $kind = $data['kind'] instanceof InviteKind ? $data['kind'] : InviteKind::from($data['kind']);
        $groups = Group::query()->where('school_id', $school->id)->whereKey(array_keys($data['groups'] ?? []))->get();

        if ($groups->count() !== count($data['groups'] ?? [])) {
            throw ValidationException::withMessages(['groups' => __('Wybrano grupę spoza tej szkoły.')]);
        }

        foreach ($groups as $group) {
            if (! Gate::forUser($creator)->allows('invite', $group)) {
                throw new AuthorizationException(__('Nie możesz zapraszać do grupy :name.', ['name' => $group->name]));
            }
            if (($data['groups'][$group->id] ?? 'member') === GroupRole::Leader->value
                && ! Gate::forUser($creator)->allows('manageLeaders', $group)) {
                throw new AuthorizationException(__('Nie możesz nadawać roli lidera w grupie :name.', ['name' => $group->name]));
            }
        }

        $role = $data['role_name'] ?? null ?: null;
        if ($role !== null) {
            Role::from($role); // valid role name
            if (! $creator->can(Permission::RolesManage->value)) {
                throw new AuthorizationException(__('Nie możesz nadawać ról w zaproszeniach.'));
            }
            if ($role === Role::Admin->value && $kind !== InviteKind::Personal) {
                throw ValidationException::withMessages(['role_name' => __('Rolę administratora można nadać tylko w zaproszeniu imiennym.')]);
            }
        }

        if ($kind === InviteKind::Personal && empty($data['email'])) {
            throw ValidationException::withMessages(['email' => __('Zaproszenie imienne wymaga adresu e-mail.')]);
        }

        if ($groups->isEmpty() && $role === null && $kind === InviteKind::GroupLink) {
            throw ValidationException::withMessages(['groups' => __('Wybierz co najmniej jedną grupę.')]);
        }

        $expiresAt = isset($data['expires_at']) && $data['expires_at'] ? Carbon::parse($data['expires_at']) : now()->addDays(config('mci.invites.default_days'));
        if ($expiresAt->isPast() || $expiresAt->gt(now()->addDays(config('mci.invites.max_days'))->endOfDay())) {
            throw ValidationException::withMessages(['expires_at' => __('Nieprawidłowa data ważności.')]);
        }

        $token = bin2hex(random_bytes(20)); // 40 hex chars, 160 bits
        $email = $kind === InviteKind::Personal ? mb_strtolower(trim((string) $data['email'])) : null;

        $invite = DB::transaction(function () use ($creator, $school, $data, $kind, $groups, $role, $expiresAt, $token, $email) {
            $invite = Invite::query()->create([
                'school_id' => $school->id,
                'token_hash' => Invite::hashToken($token),
                'token' => $token,
                'kind' => $kind,
                'label' => $data['label'] ?? null ?: null,
                'email' => $email,
                'user_id' => $email ? User::query()->where('email', $email)->value('id') : null,
                'role_name' => $role,
                'max_uses' => $kind === InviteKind::Personal ? 1 : ($data['max_uses'] ?? null ?: null),
                'allow_self_registration' => $kind === InviteKind::GroupLink && (bool) ($data['allow_self_registration'] ?? false),
                'expires_at' => $expiresAt,
                'created_by' => $creator->id,
            ]);

            $invite->groups()->attach($groups->mapWithKeys(fn (Group $g) => [
                $g->id => ['group_role' => $data['groups'][$g->id] ?? GroupRole::Member->value],
            ])->all());

            Audit::log('invite.created', $invite, [
                'kind' => $kind->value,
                'groups' => $groups->pluck('id')->all(),
                'role' => $role,
                'email' => $email,
                'max_uses' => $invite->max_uses,
                'expires_at' => $expiresAt->toIso8601String(),
            ], $creator, $school->id);

            return $invite;
        });

        if ($email && ($data['send_email'] ?? true)) {
            Notification::route('mail', $email)->notify(new InviteNotification($invite, $token));
        }

        return [$invite, $token];
    }
}
