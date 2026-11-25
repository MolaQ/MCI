<?php

namespace App\Livewire\Admin;

use App\Domain\Media\Actions\StoreImage;
use App\Livewire\Concerns\WithImageUploads;
use App\Models\Media;
use App\Support\Appearance as AppearanceService;
use App\Support\Audit;
use App\Support\Settings;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Wygląd')]
class Appearance extends Component
{
    use WithImageUploads;

    public string $school_name = '';

    public string $short_name = '';

    public string $primary_color = '';

    public string $primary_foreground = '';

    public string $danger_color = '';

    public string $radius = 'md';

    public string $default_theme = 'system';

    public $logo;

    #[Locked]
    public ?string $logo_path = null;

    #[Locked]
    public ?int $logo_media_id = null;

    public function mount(AppearanceService $appearance): void
    {
        $this->authorize('site-admin');

        $this->fill(collect($appearance->all())->only([
            'school_name', 'short_name', 'primary_color', 'primary_foreground', 'danger_color', 'radius', 'default_theme', 'logo_path', 'logo_media_id',
        ])->all());
    }

    protected function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'school_name' => ['required', 'string', 'max:150'],
            'short_name' => ['required', 'string', 'max:20'],
            'primary_color' => $hex,
            'primary_foreground' => $hex,
            'danger_color' => $hex,
            'radius' => ['required', Rule::in(array_keys(AppearanceService::RADII))],
            'default_theme' => ['required', Rule::in(AppearanceService::THEMES)],
            'logo' => $this->imageUploadRule(),
        ];
    }

    public function updatedLogo(): void
    {
        $this->validateOnly('logo');
    }

    public function save(Settings $settings, StoreImage $store)
    {
        $this->authorize('site-admin');
        $data = $this->validate();
        unset($data['logo']);

        $previous = $this->currentLogo();
        if ($this->logo) {
            // Store the new file first; the old one goes only after the setting is saved.
            $this->logo_media_id = $store->handle($this->logo, 'logo', null, Auth::user(), public: true, field: 'logo')->id;
            $this->logo_path = null;
        }

        $data['logo_path'] = $this->logo_path;
        $data['logo_media_id'] = $this->logo_media_id;
        $settings->set('appearance', $data);

        if ($this->logo) {
            $this->discardLegacyLogo($previous);
        }
        Audit::site('settings.appearance_updated', null, ['keys' => array_keys($data), 'logo_changed' => (bool) $this->logo]);

        Flux::toast(variant: 'success', text: __('Zapisano wygląd.'));

        // Full reload so the new CSS variables are applied in <head>.
        return $this->redirectRoute('site.appearance');
    }

    public function removeLogo(Settings $settings): void
    {
        $this->authorize('site-admin');

        $previous = $this->currentLogo();
        $this->logo_path = null;
        $this->logo_media_id = null;
        $settings->set('appearance', array_merge((array) $settings->get('appearance', []), ['logo_path' => null, 'logo_media_id' => null]));
        $this->discardLegacyLogo($previous);
        Audit::site('settings.logo_removed');

        Flux::toast(text: __('Usunięto logo.'));
    }

    /**
     * @return array{0: ?Media, 1: ?string}
     */
    private function currentLogo(): array
    {
        return [$this->logo_media_id ? Media::query()->find($this->logo_media_id) : null, $this->logo_path];
    }

    /**
     * @param  array{0: ?Media, 1: ?string}  $previous
     */
    private function discardLegacyLogo(array $previous): void
    {
        [$media, $path] = $previous;
        $media?->purge();
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function resetDefaults(Settings $settings)
    {
        $this->authorize('site-admin');
        $settings->set('appearance', ['logo_path' => $this->logo_path, 'logo_media_id' => $this->logo_media_id]);
        Audit::site('settings.appearance_reset');

        return $this->redirectRoute('site.appearance');
    }

    public function render()
    {
        return view('livewire.admin.appearance', [
            'logoUrl' => app(AppearanceService::class)->logoUrl(),
            'radii' => AppearanceService::RADII,
            'themes' => AppearanceService::THEMES,
        ]);
    }
}
