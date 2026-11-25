<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\MembershipMode;
use App\Support\Appearance;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property GroupType $type
 * @property MembershipMode $membership_mode
 * @property bool $is_system
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'type',
        'description',
        'color',
        'is_system',
        'membership_mode',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => GroupType::class,
            'membership_mode' => MembershipMode::class,
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            $group->slug ??= static::uniqueSlug($group->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'grupa';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(GroupMembership::class)
            ->withPivot(['role', 'joined_via', 'invite_id'])
            ->withTimestamps();
    }

    public function leaders(): BelongsToMany
    {
        return $this->members()->wherePivot('role', GroupRole::Leader->value);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayColor(): string
    {
        return Appearance::sanitizeHex($this->color, $this->type->color());
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%');
        }
    }
}
