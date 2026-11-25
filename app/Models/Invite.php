<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\InviteKind;
use App\Enums\InviteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_id
 * @property string $token (encrypted at rest)
 * @property InviteKind $kind
 * @property ?string $email
 * @property ?string $role_name
 * @property ?int $max_uses
 * @property int $uses_count
 * @property bool $allow_self_registration
 * @property ?Carbon $expires_at
 * @property ?Carbon $revoked_at
 */
class Invite extends Model
{
    protected $fillable = [
        'school_id', 'token_hash', 'token', 'kind', 'label', 'email', 'user_id', 'role_name',
        'max_uses', 'allow_self_registration', 'expires_at', 'created_by',
    ];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'kind' => InviteKind::class,
            'allow_self_registration' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        // Cheap shape check first: never hit the DB for obviously bogus input.
        if (! preg_match('/^[A-Za-z0-9]{32,64}$/', $token)) {
            return null;
        }

        return static::query()->with(['school', 'groups'])->where('token_hash', static::hashToken($token))->first();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'invite_group')->withPivot('group_role');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(InviteRedemption::class);
    }

    public function status(): InviteStatus
    {
        return match (true) {
            $this->revoked_at !== null => InviteStatus::Revoked,
            $this->expires_at !== null && $this->expires_at->isPast() => InviteStatus::Expired,
            $this->max_uses !== null && $this->uses_count >= $this->max_uses => InviteStatus::UsedUp,
            default => InviteStatus::Active,
        };
    }

    public function isUsable(): bool
    {
        return $this->status() === InviteStatus::Active
            && $this->school !== null
            && $this->school->is_active;
    }

    public function url(): string
    {
        return route('join.show', $this->token);
    }

    public function groupRole(Group $group): GroupRole
    {
        return GroupRole::from($group->pivot->group_role ?? GroupRole::Member->value);
    }

    public function scopeWithStatus(Builder $query, InviteStatus $status): void
    {
        match ($status) {
            InviteStatus::Revoked => $query->whereNotNull('revoked_at'),
            InviteStatus::Expired => $query->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            InviteStatus::UsedUp => $query->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->whereNotNull('max_uses')->whereColumn('uses_count', '>=', 'max_uses'),
            InviteStatus::Active => $query->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses')),
        };
    }
}
