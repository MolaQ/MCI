<?php

namespace App\Support;

use App\Enums\SchoolMemberStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * The school the signed-in user is working in. Every permission check is
 * evaluated in this school (Spatie "teams"), and school-owned lists are
 * scoped to it. See docs/ARCHITECTURE.md §3.0.2.
 */
class CurrentSchool
{
    public const SESSION_KEY = 'mci.school_id';

    private ?School $school = null;

    public function get(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function required(): School
    {
        abort_if($this->school === null, 403, __('Najpierw wybierz szkołę.'));

        return $this->school;
    }

    public function set(?School $school, ?User $user = null): void
    {
        $this->school = $school;
        app(PermissionRegistrar::class)->setPermissionsTeamId($school?->id);

        // Role / permission relations are cached per model; reload them for the new school.
        $user?->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * Schools the user may switch to: active memberships, or every active
     * school for site admins.
     *
     * @return Collection<int, School>
     */
    public function availableFor(User $user): Collection
    {
        if ($user->is_site_admin) {
            return School::query()->where('is_active', true)->orderBy('name')->get();
        }

        return $user->schools()
            ->where('schools.is_active', true)
            ->wherePivot('status', SchoolMemberStatus::Active->value)
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve the active school for a request: session choice if still
     * allowed, else the last used one, else the first available.
     */
    public function resolveFor(User $user, ?int $preferredId): ?School
    {
        $available = $this->availableFor($user);

        return $available->firstWhere('id', $preferredId)
            ?? $available->firstWhere('id', $user->last_school_id)
            ?? $available->first();
    }
}
