<?php

namespace App\Livewire\Admin;

use App\Domain\Media\Actions\ReplaceImage;
use App\Enums\Permission;
use App\Enums\SchoolOfferKind;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\School;
use App\Models\SchoolOffer;
use App\Support\Audit;
use App\Support\CurrentSchool;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What the active school shows on the public front: description, contact
 * details and educational offer. Requires settings.manage in that school.
 */
#[Title('Profil publiczny szkoły')]
class SchoolProfile extends Component
{
    use WithImageUploads;

    public $logoUpload = null;

    public string $tagline = '';

    public string $description = '';

    public string $address = '';

    public string $phone = '';

    public string $public_email = '';

    public string $website = '';

    public bool $is_listed = true;

    public string $offerKind = 'technikum';

    public string $offerName = '';

    public string $offerDescription = '';

    public function mount(): void
    {
        $this->authorizeAccess();
        $school = $this->school();
        foreach (['tagline', 'description', 'address', 'phone', 'public_email', 'website'] as $field) {
            $this->{$field} = (string) $school->{$field};
        }
        $this->is_listed = (bool) ($school->is_listed ?? true);
    }

    private function authorizeAccess(): void
    {
        abort_unless(app(CurrentSchool::class)->id() && Auth::user()->can(Permission::SettingsManage->value), 403);
    }

    private function school(): School
    {
        return app(CurrentSchool::class)->required();
    }

    #[Computed]
    public function offers()
    {
        return $this->school()->offers()->get();
    }

    public function updatedLogoUpload(): void
    {
        $this->validateOnly('logoUpload', ['logoUpload' => $this->imageUploadRule()]);
    }

    public function removeLogo(ReplaceImage $replace): void
    {
        $this->authorizeAccess();
        $replace->handle($this->school(), 'logo_media_id', null, 'logo', true, Auth::user(), 'logoUpload', remove: true);
        Flux::toast(text: __('Usunięto logo szkoły.'));
    }

    public function save(ReplaceImage $replace): void
    {
        $this->authorizeAccess();
        $data = $this->validate([
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:32'],
            'public_email' => ['nullable', 'email', 'max:191'],
            'website' => ['nullable', 'url:https,http', 'max:191'],
            'is_listed' => ['boolean'],
            'logoUpload' => $this->imageUploadRule(),
        ]);
        unset($data['logoUpload']);

        $school = $this->school();
        $school->update(array_map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v, $data));
        Audit::log('school.public_profile_updated', $school, ['changed' => array_keys($school->getChanges())]);

        if ($this->logoUpload) {
            $replace->handle($school, 'logo_media_id', $this->logoUpload, 'logo', true, Auth::user(), 'logoUpload');
            $this->logoUpload = null;
        }

        Flux::toast(variant: 'success', text: __('Zapisano profil publiczny.'));
    }

    public function addOffer(): void
    {
        $this->authorizeAccess();
        $this->validate([
            'offerKind' => ['required', Rule::enum(SchoolOfferKind::class)],
            'offerName' => ['required', 'string', 'max:150'],
            'offerDescription' => ['nullable', 'string', 'max:1000'],
        ], [], ['offerName' => __('nazwa kierunku')]);

        $offer = $this->school()->offers()->create([
            'kind' => $this->offerKind,
            'name' => trim($this->offerName),
            'description' => trim($this->offerDescription) ?: null,
            'position' => (int) $this->school()->offers()->max('position') + 1,
        ]);
        Audit::log('school.offer_added', $this->school(), ['offer' => $offer->name]);

        $this->reset('offerName', 'offerDescription');
        unset($this->offers);
    }

    public function toggleOffer(int $id): void
    {
        $this->authorizeAccess();
        $offer = $this->findOffer($id);
        $offer->update(['is_active' => ! $offer->is_active]);
        unset($this->offers);
    }

    public function moveOffer(int $id, int $direction): void
    {
        $this->authorizeAccess();
        $offers = $this->school()->offers()->get()->values();
        $index = $offers->search(fn ($o) => $o->id === $id);
        $swap = $index === false ? null : $offers->get($index + ($direction < 0 ? -1 : 1));

        if ($swap) {
            $current = $offers[$index];
            [$a, $b] = [$current->position, $swap->position];
            $current->update(['position' => $b === $a ? $a + $direction : $b]);
            $swap->update(['position' => $a]);
        }
        unset($this->offers);
    }

    public function deleteOffer(int $id): void
    {
        $this->authorizeAccess();
        $offer = $this->findOffer($id);
        Audit::log('school.offer_removed', $this->school(), ['offer' => $offer->name]);
        $offer->delete();
        unset($this->offers);
    }

    private function findOffer(int $id): SchoolOffer
    {
        return SchoolOffer::query()->where('school_id', $this->school()->id)->findOrFail($id);
    }

    public function render()
    {
        $this->authorizeAccess();

        return view('livewire.admin.school-profile', ['kinds' => SchoolOfferKind::cases(), 'school' => $this->school()]);
    }
}
