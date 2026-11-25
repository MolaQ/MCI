<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\Role;
use App\Enums\UserStatus;
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

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->using(GroupMembership::class)
            ->withPivot(['role', 'joined_via', 'invite_id'])
            ->withTimestamps();
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
