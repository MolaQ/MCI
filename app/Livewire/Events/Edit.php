<?php

namespace App\Livewire\Events;

use App\Domain\Media\Actions\ReplaceImage;
use App\Enums\ContentVisibility;
use App\Enums\EventCategory;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\Event;
use App\Support\Audit;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Edit extends Component
{
    use WithImageUploads;

    public $coverUpload = null;

    #[Locked]
    public ?string $coverUrl = null;

    #[Locked]
    public ?int $eventId = null;

    public string $title = '';

    public string $category = 'other';

    public string $date = '';

    public string $start_time = '';

    public string $end_date = '';

    public string $end_time = '';

    public bool $all_day = false;

    public string $location = '';

    public string $visibility = 'school_only';

    public string $description = '';

    public string $public_description = '';

    public bool $cancelled = false;

    public function mount(?Event $event = null): void
    {
        if ($event?->exists) {
            $this->authorize('update', $event);
            $this->eventId = $event->id;
            $this->title = $event->title;
            $this->location = (string) $event->location;
            $this->all_day = (bool) $event->all_day;
            $this->category = $event->category->value;
            $this->visibility = $event->visibility->value;
            $this->description = (string) $event->description;
            $this->public_description = (string) $event->public_description;
            $this->date = $event->starts_at->format('Y-m-d');
            $this->start_time = $event->all_day ? '' : $event->starts_at->format('H:i');
            $this->end_date = $event->ends_at?->format('Y-m-d') ?? '';
            $this->end_time = $event->ends_at && ! $event->all_day ? $event->ends_at->format('H:i') : '';
            $this->cancelled = $event->status === 'cancelled';
            $this->coverUrl = $event->cover?->url();
        } else {
            $this->authorize('create', Event::class);
            $this->date = now()->addWeek()->format('Y-m-d');
            $this->start_time = '10:00';
        }
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(EventCategory::class)],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => [$this->all_day ? 'nullable' : 'required', 'nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:150'],
            'visibility' => ['required', Rule::in(array_map(fn ($v) => $v->value, ContentVisibility::selectable()))],
            'description' => ['nullable', 'string', 'max:5000'],
            'public_description' => ['nullable', 'string', 'max:3000'],
            'coverUpload' => $this->imageUploadRule(),
        ];
    }

    protected function validationAttributes(): array
    {
        return ['title' => __('nazwa'), 'date' => __('data'), 'start_time' => __('godzina rozpoczęcia'), 'end_date' => __('data zakończenia')];
    }

    public function save()
    {
        $event = $this->eventId ? Event::query()->findOrFail($this->eventId) : null;
        $this->authorize($event ? 'update' : 'create', $event ?? Event::class);
        $this->validate();

        if ($this->visibility === ContentVisibility::Public->value && ! Auth::user()->can('publishPublic', Event::class)) {
            $this->addError('visibility', __('Nie możesz publikować wydarzeń publicznie. Wybierz inny poziom widoczności.'));

            return null;
        }

        $startsAt = CarbonImmutable::parse($this->date.' '.($this->all_day ? '00:00' : $this->start_time));
        $endsAt = null;
        if ($this->end_date !== '' || $this->end_time !== '') {
            $endsAt = CarbonImmutable::parse(($this->end_date ?: $this->date).' '.($this->all_day ? '23:59' : ($this->end_time ?: $this->start_time)));
            if ($endsAt->lt($startsAt)) {
                $this->addError('end_time', __('Koniec musi być po rozpoczęciu.'));

                return null;
            }
        }

        $data = [
            'title' => trim($this->title),
            'category' => $this->category,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $this->all_day,
            'location' => trim($this->location) ?: null,
            'visibility' => $this->visibility,
            'description' => trim($this->description) ?: null,
            'public_description' => trim($this->public_description) ?: null,
            'status' => $this->cancelled ? 'cancelled' : 'planned',
        ];

        if ($event) {
            $event->update($data);
            Audit::log('event.updated', $event, ['changed' => array_keys($event->getChanges())]);
        } else {
            $event = Event::query()->create($data + ['school_id' => app(CurrentSchool::class)->id(), 'owner_id' => Auth::id()]);
            Audit::log('event.created', $event, ['title' => $event->title, 'visibility' => $event->visibility->value]);
        }

        // The cover may leave the building only together with a public event.
        $public = $event->visibility === ContentVisibility::Public;
        app(ReplaceImage::class)->handle($event, 'cover_media_id', $this->coverUpload, 'cover', $public, Auth::user(), 'coverUpload');
        ReplaceImage::syncVisibility($event->cover_media_id, $public);

        Flux::toast(variant: 'success', text: __('Zapisano wydarzenie.'));

        return $this->redirectRoute('events.show', $event, navigate: true);
    }

    public function updatedCoverUpload(): void
    {
        $this->validateOnly('coverUpload');
    }

    public function removeCover(ReplaceImage $replace): void
    {
        $event = Event::query()->findOrFail($this->eventId);
        $this->authorize('update', $event);
        $replace->handle($event, 'cover_media_id', null, 'cover', false, Auth::user(), 'coverUpload', remove: true);
        $this->coverUrl = null;
    }

    public function delete()
    {
        $event = Event::query()->findOrFail($this->eventId);
        $this->authorize('delete', $event);

        Audit::log('event.deleted', $event, ['title' => $event->title]);
        $event->delete();
        Flux::toast(text: __('Usunięto wydarzenie.'));

        return $this->redirectRoute('events.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.events.edit', [
            'categories' => EventCategory::cases(),
            'visibilities' => ContentVisibility::selectable(),
            'canPublishPublic' => Auth::user()->can('publishPublic', Event::class),
        ])->title($this->eventId ? $this->title : __('Nowe wydarzenie'));
    }
}
