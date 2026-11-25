<?php

namespace App\Models;

use App\Enums\AnnouncementPriority;
use App\Enums\Feature;
use App\Support\Features;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property AnnouncementPriority $priority
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'author_id', 'title', 'body', 'priority', 'requires_confirmation',
        'is_public', 'published_at', 'expires_at', 'pinned_until', 'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'priority' => AnnouncementPriority::class,
            'requires_confirmation' => 'boolean',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'pinned_until' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Images attached to the post, in order. */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'owner')->where('collection', 'image')->orderBy('position');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_recipients')
            ->withPivot(['reason', 'notified_at', 'read_at', 'confirmed_at']);
    }

    public function audience(): MorphMany
    {
        return $this->morphMany(AudienceTarget::class, 'owner');
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'source');
    }

    /** Published (not scheduled for later) and not expired. */
    public function scopeLive(Builder $query): void
    {
        $query->whereNotNull('announcements.published_at')
            ->where('announcements.published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('announcements.expires_at')->orWhere('announcements.expires_at', '>', now()));
    }

    public function scopePubliclyVisible(Builder $query): void
    {
        $features = app(Features::class);
        $query->live()->where('announcements.is_public', true)
            ->whereHas('school', fn ($s) => $s->where('is_active', true)->where('is_listed', true))
            ->whereNotIn('announcements.school_id', $features->schoolsWithout(Feature::PublicFront)->merge($features->schoolsWithout(Feature::PublicAnnouncements)));
    }

    public function isLive(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isPinned(): bool
    {
        return $this->pinned_until !== null && $this->pinned_until->isFuture();
    }
}
