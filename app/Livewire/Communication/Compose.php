<?php

namespace App\Livewire\Communication;

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Domain\Communication\Audience;
use App\Domain\Communication\AudienceGuard;
use App\Enums\AnnouncementPriority;
use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\Announcement;
use App\Models\User;
use App\Support\CurrentSchool;
use App\Support\Mentions;
use App\Support\RichText;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

#[Title('Nowy komunikat')]
class Compose extends Component
{
    use WithImageUploads;

    /** The editor uploads one finished image here; it is moved into $images. */
    public $newImage = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $images = [];

    public string $title = '';

    public string $body = '';

    public string $priority = 'normal';

    /** @var list<string> audience keys, see App\Domain\Communication\Audience */
    public array $audience = [];

    public bool $includeSubgroups = false;

    public string $peopleSearch = '';

    public string $groupFilter = '';

    public bool $requires_confirmation = false;

    public bool $pinned = false;

    public bool $is_public = false;

    public string $when = 'now';

    public string $publish_at = '';

    public string $expiry = '';

    #[Url(as: 'grupa', except: '')]
    public string $preselectGroup = '';

    public function mount(AudienceGuard $guard): void
    {
        $this->authorize('create', Announcement::class);

        if ($this->preselectGroup !== '' && $this->groups->contains('id', (int) $this->preselectGroup)) {
            $this->audience[] = 'group:'.(int) $this->preselectGroup;
        }
    }

    #[Computed]
    public function groups()
    {
        return app(AudienceGuard::class)->targetableGroups(Auth::user());
    }

    #[Computed]
    public function canTargetSchool(): bool
    {
        return app(AudienceGuard::class)->canTargetWholeSchool(Auth::user());
    }

    #[Computed]
    public function canPublishPublic(): bool
    {
        return Auth::user()->can(Permission::EventsPublishPublic->value);
    }

    #[Computed]
    public function peopleResults()
    {
        if (mb_strlen(trim($this->peopleSearch)) < 2) {
            return collect();
        }

        return User::query()->activeInSchool(app(CurrentSchool::class)->id())->active()
            ->search($this->peopleSearch)
            ->whereKeyNot(Auth::id())
            ->orderBy('last_name')->limit(6)->get();
    }

    #[Computed]
    public function selectedPeople()
    {
        $ids = collect($this->audience)->filter(fn ($k) => str_starts_with($k, 'user:'))->map(fn ($k) => (int) substr($k, 5));

        return User::query()->whereKey($ids)->orderBy('last_name')->get();
    }

    /** Live "reaches N people" preview (mentions are added on publish). */
    #[Computed]
    public function reach(): int
    {
        try {
            return Audience::fromKeys($this->effectiveAudience())
                ->resolve(app(CurrentSchool::class)->required())
                ->reject(fn ($id) => $id === Auth::id())
                ->count();
        } catch (ValidationException) {
            return 0;
        }
    }

    #[Computed]
    public function mentionsInBody(): int
    {
        return substr_count($this->body, 'data-type="mention"');
    }

    public function toggle(string $key): void
    {
        $this->audience = in_array($key, $this->audience, true)
            ? array_values(array_diff($this->audience, [$key]))
            : [...$this->audience, $key];

        if ($key === 'everyone' && in_array('everyone', $this->audience, true)) {
            // "Cała szkoła" already covers everyone else.
            $this->audience = ['everyone'];
        }
    }

    public function selectMyGroups(): void
    {
        $mine = Auth::user()->schoolGroups()->pluck('groups.id');
        $keys = $this->groups->whereIn('id', $mine)->map(fn ($g) => 'group:'.$g->id);
        $this->audience = collect($this->audience)->reject(fn ($k) => $k === 'everyone')->merge($keys)->unique()->values()->all();
    }

    public function addPerson(int $id): void
    {
        if (! in_array("user:{$id}", $this->audience, true)) {
            $this->audience[] = "user:{$id}";
        }
        $this->peopleSearch = '';
    }

    public function clearAudience(): void
    {
        $this->audience = [];
    }

    /**
     * @return list<string>
     */
    private function effectiveAudience(): array
    {
        if (! $this->includeSubgroups) {
            return $this->audience;
        }

        $guard = app(AudienceGuard::class);

        return array_map(function ($key) use ($guard) {
            if (preg_match('/^group:(\d+)$/', $key, $m) && ($g = $this->groups->firstWhere('id', (int) $m[1])) && $guard->canIncludeSubgroups(Auth::user(), $g)) {
                return 'group+:'.$m[1];
            }

            return $key;
        }, $this->audience);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:100000'],
            'priority' => ['required', Rule::enum(AnnouncementPriority::class)],
            'audience' => ['array'],
            'audience.*' => ['string', 'max:40'],
            'when' => ['required', Rule::in(['now', 'later'])],
            'publish_at' => ['nullable', 'required_if:when,later', 'date', 'after:now'],
            'expiry' => ['nullable', Rule::in(['', '1', '7', '30'])],
            'images' => ['array', 'max:'.config('mci.media.max_post_images')],
            'images.*' => $this->imageUploadRule(),
        ];
    }

    protected function validationAttributes(): array
    {
        return ['title' => __('tytuł'), 'body' => __('treść'), 'publish_at' => __('termin publikacji')];
    }

    public function updatedNewImage(): void
    {
        $this->validateOnly('newImage', ['newImage' => $this->imageUploadRule()]);

        if (count($this->images) >= config('mci.media.max_post_images')) {
            $this->addError('newImage', __('Możesz dodać najwyżej :count obrazów.', ['count' => config('mci.media.max_post_images')]));
        } elseif ($this->newImage) {
            $this->images[] = $this->newImage;
        }
        $this->newImage = null;
    }

    public function removeImage(int $index): void
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function moveImage(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (isset($this->images[$index], $this->images[$target])) {
            [$this->images[$index], $this->images[$target]] = [$this->images[$target], $this->images[$index]];
        }
    }

    public function save(PublishAnnouncement $publish)
    {
        $this->authorize('create', Announcement::class);
        $this->validate();

        $publishAt = $this->when === 'later' && $this->publish_at ? Carbon::parse($this->publish_at) : now();

        try {
            $announcement = $publish->handle(Auth::user(), app(CurrentSchool::class)->required(), [
                'title' => $this->title,
                'body' => $this->body,
                'priority' => $this->priority,
                'requires_confirmation' => $this->requires_confirmation,
                'is_public' => $this->is_public && $this->canPublishPublic,
                'publish_at' => $this->when === 'later' ? $publishAt : null,
                'expires_at' => $this->expiry !== '' ? $publishAt->copy()->addDays((int) $this->expiry) : null,
                'pinned' => $this->pinned,
            ], $this->effectiveAudience(), $this->images);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }

        Flux::toast(variant: 'success', text: $announcement->published_at->isFuture()
            ? __('Zaplanowano komunikat na :date.', ['date' => $announcement->published_at->translatedFormat('j F, H:i')])
            : trans_choice('Wysłano do :count osoby.|Wysłano do :count osób.|Wysłano do :count osób.', $announcement->recipients_count));

        return $this->redirectRoute('communication.show', $announcement, navigate: true);
    }

    public function render()
    {
        return view('livewire.communication.compose', [
            'priorities' => AnnouncementPriority::cases(),
            'roles' => array_filter(Role::cases(), fn (Role $r) => $r !== Role::Admin),
            'publicPreview' => $this->is_public ? Mentions::forPublic(RichText::sanitize($this->body)) : null,
        ]);
    }
}
