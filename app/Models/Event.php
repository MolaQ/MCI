<?php

namespace App\Models;

use App\Casts\AsTaxonomyTerm;
use App\Enums\ContentVisibility;
use App\Enums\Feature;
use App\Support\Features;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A dated event. Step 3 ships the core (visibility, category, time, place);
 * stages, tasks and members are added by the Project Manager step.
 *
 * @property ContentVisibility $visibility
 * @property TaxonomyTerm $category
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    /** Fields that may ever leave the building (public pages, iCal, TV). */
    public const PUBLIC_COLUMNS = ['id', 'school_id', 'title', 'public_description', 'category', 'starts_at', 'ends_at', 'all_day', 'location', 'visibility', 'status', 'cover_media_id'];

    protected $fillable = [
        'school_id', 'owner_id', 'title', 'description', 'public_description', 'category',
        'starts_at', 'ends_at', 'all_day', 'location', 'visibility', 'status',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => ContentVisibility::class,
            'category' => AsTaxonomyTerm::class.':event_category',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Public, not cancelled, owned by an active listed school. Selects only
     * PUBLIC_COLUMNS so internal fields can never reach a public view.
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->select(array_map(fn ($c) => 'events.'.$c, self::PUBLIC_COLUMNS))
            ->where('events.visibility', ContentVisibility::Public->value)
            ->where('events.status', '!=', 'cancelled')
            ->whereHas('school', fn ($s) => $s->where('is_active', true)->where('is_listed', true))
            ->whereNotIn('events.school_id', app(Features::class)->schoolsWithout(Feature::PublicEvents)->merge(app(Features::class)->schoolsWithout(Feature::PublicFront)));
    }

    /**
     * What a logged-in user may see: public, inter-school (if they belong to
     * any active school) and their own schools' school-only events.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $schoolIds = $user->schools()->wherePivot('status', 'active')->where('schools.is_active', true)->pluck('schools.id');

        $query->where(function (Builder $q) use ($schoolIds) {
            $q->where('visibility', ContentVisibility::Public->value)
                ->when($schoolIds->isNotEmpty(), fn ($q) => $q
                    ->orWhere('visibility', ContentVisibility::InterSchool->value)
                    ->orWhere(fn ($q) => $q->where('visibility', ContentVisibility::SchoolOnly->value)->whereIn('school_id', $schoolIds)));
        });
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where(fn ($q) => $q->where('starts_at', '>=', now()->startOfDay())->orWhere('ends_at', '>=', now()));
    }

    public function isPublic(): bool
    {
        return $this->visibility === ContentVisibility::Public;
    }

    public function timeLabel(): string
    {
        $start = $this->starts_at;
        $end = $this->ends_at;

        if ($this->all_day) {
            return $end && ! $end->isSameDay($start)
                ? $start->translatedFormat('j M').' – '.$end->translatedFormat('j M Y')
                : $start->translatedFormat('j F Y');
        }

        $label = $start->translatedFormat('j F Y, H:i');
        if ($end) {
            $label .= ' – '.($end->isSameDay($start) ? $end->format('H:i') : $end->translatedFormat('j F, H:i'));
        }

        return $label;
    }
}
