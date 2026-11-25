<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Enums\SchoolMemberStatus;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $short_name
 * @property string $slug
 * @property array<int, string>|null $email_domains
 * @property array<string, mixed>|null $settings
 * @property bool $is_active
 */
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'short_name', 'slug', 'city', 'email_domains', 'settings', 'logo_path', 'color', 'is_active'];

    protected function casts(): array
    {
        return [
            'email_domains' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (School $school) {
            $school->slug ??= Str::slug($school->short_name ?: $school->name);
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['status', 'joined_via', 'invite_id'])->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', SchoolMemberStatus::Active->value);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function maxProfileVisibility(): ProfileVisibility
    {
        return ProfileVisibility::tryFrom((string) $this->setting('max_profile_visibility')) ?? ProfileVisibility::Network;
    }

    public function allowsSelfRegistration(): bool
    {
        return (bool) $this->setting('allow_self_registration', true);
    }

    /**
     * True when no domains are configured, or the address is in one of them.
     */
    public function acceptsEmailDomain(string $email): bool
    {
        $domains = array_filter(array_map(fn ($d) => mb_strtolower(trim($d)), $this->email_domains ?? []));

        if ($domains === []) {
            return true;
        }

        $domain = mb_strtolower(Str::after($email, '@'));

        return in_array($domain, $domains, true);
    }
}
