<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\ProfileVisibility;
use App\Enums\Role;
use App\Enums\SchoolMemberStatus;
use App\Enums\UserStatus;
use App\Support\CurrentSchool;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property UserStatus $status
 * @property-read string $name
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'status',
        'locale',
        'notification_prefs',
        'profile_visibility',
        'profile_fields',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @var array<int, int>|null memoized ids of groups this user leads */
    protected ?array $ledGroupIdsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'notification_prefs' => 'array',
            'is_site_admin' => 'boolean',
            'profile_visibility' => ProfileVisibility::class,
            'profile_fields' => 'array',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    public function initials(): string
    {
        return Str::upper(Str::substr($this->first_name, 0, 1).Str::substr($this->last_name, 0, 1));
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->withPivot(['status', 'joined_via', 'invite_id'])
            ->withTimestamps();
    }

    public function belongsToSchool(School|int|null $school, bool $activeOnly = true): bool
    {
        $id = $school instanceof School ? $school->id : $school;

        if ($id === null) {
            return false;
        }

        $query = $this->schools()->whereKey($id);

        if ($activeOnly) {
            $query->wherePivot('status', SchoolMemberStatus::Active->value);
        }

        return $query->exists();
    }

    /**
     * Membership status in a school (null when not a member).
     */
    public function schoolStatus(School|int $school): ?SchoolMemberStatus
    {
        $id = $school instanceof School ? $school->id : $school;
        $pivot = $this->schools()->whereKey($id)->first()?->pivot;

        return $pivot ? SchoolMemberStatus::from($pivot->status) : null;
    }

    /**
     * Groups of the active school only.
     */
    public function schoolGroups(): BelongsToMany
    {
        return $this->groups()->where('groups.school_id', app(CurrentSchool::class)->id());
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->using(GroupMembership::class)
            ->withPivot(['role', 'joined_via', 'invite_id'])
            ->withTimestamps();
    }

    public function scopeInSchool(Builder $query, School|int|null $school): void
    {
        $id = $school instanceof School ? $school->id : $school;
        $query->whereHas('schools', fn (Builder $q) => $q->whereKey($id));
    }

    /**
     * The person's choice, narrowed by the strictest cap among their schools.
     */
    public function effectiveProfileVisibility(): ProfileVisibility
    {
        $caps = $this->schools()->get()->map(fn (School $s) => $s->maxProfileVisibility())->all();

        return ProfileVisibility::strictest($this->profile_visibility ?? ProfileVisibility::School, ...$caps);
    }

    /**
     * Whether $viewer (null = anonymous visitor) may see this profile.
     */
    public function profileVisibleTo(?User $viewer): bool
    {
        if ($viewer?->is($this) || $viewer?->is_site_admin) {
            return true;
        }

        if ($viewer && $viewer->schools()->whereIn('schools.id', $this->schools()->pluck('schools.id'))->exists()) {
            return true;
        }

        $level = $this->effectiveProfileVisibility();

        return $viewer
            ? $level->rank() >= ProfileVisibility::Network->rank()
            : $level === ProfileVisibility::Public;
    }

    public function ledGroups(): BelongsToMany
    {
        return $this->groups()->wherePivot('role', GroupRole::Leader->value);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<int, int>
     */
    public function ledGroupIds(): array
    {
        return $this->ledGroupIdsCache ??= $this->ledGroups()->pluck('groups.id')->all();
    }

    public function flushLeadershipCache(): void
    {
        $this->ledGroupIdsCache = null;
    }

    public function leadsGroup(Group|int $group): bool
    {
        $id = $group instanceof Group ? $group->id : $group;

        return in_array($id, $this->ledGroupIds(), true);
    }

    public function leadsAnyGroup(): bool
    {
        return $this->ledGroupIds() !== [];
    }

    /**
     * Whether $other is a member of at least one group this user leads.
     */
    public function leadsAnyGroupContaining(User $other): bool
    {
        if (! $this->leadsAnyGroup()) {
            return false;
        }

        return $other->groups()->whereIn('groups.id', $this->ledGroupIds())->exists();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * School administrator in the active school.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::Active->value);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            foreach (preg_split('/\s+/', $term) as $word) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
                $q->where(fn (Builder $w) => $w
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like));
            }
        });
    }
}
