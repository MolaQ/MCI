<?php

namespace App\Livewire\Admin;

use App\Support\Appearance as AppearanceService;
use App\Support\Audit;
use App\Support\Settings;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Wygląd')]
class Appearance extends Component
{
    use WithFileUploads;

    public string $school_name = '';

    public string $short_name = '';

    public string $primary_color = '';

    public string $primary_foreground = '';

    public string $danger_color = '';

    public string $radius = 'md';

    public string $default_theme = 'system';

    public $logo;

    public ?string $logo_path = null;

    public function mount(AppearanceService $appearance): void
    {
        $this->authorize('site-admin');

        $this->fill(collect($appearance->all())->only([
            'school_name', 'short_name', 'primary_color', 'primary_foreground', 'danger_color', 'radius', 'default_theme', 'logo_path',
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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ];
    }

    public function save(Settings $settings)
    {
        $this->authorize('site-admin');
        $data = $this->validate();

        if ($this->logo) {
            if ($this->logo_path) {
                Storage::disk('public')->delete($this->logo_path);
            }
            $this->logo_path = $this->logo->store('branding', 'public');
        }

        unset($data['logo']);
        $data['logo_path'] = $this->logo_path;

        $settings->set('appearance', $data);
        Audit::site('settings.appearance_updated', null, ['keys' => array_keys($data)]);

        Flux::toast(variant: 'success', text: __('Zapisano wygląd.'));

        // Full reload so the new CSS variables are applied in <head>.
        return $this->redirectRoute('site.appearance');
    }

    public function removeLogo(Settings $settings): void
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
        }
        $this->logo_path = null;
        $settings->set('appearance', array_merge((array) $settings->get('appearance', []), ['logo_path' => null]));
    }

    public function resetDefaults(Settings $settings)
    {
        $settings->set('appearance', ['logo_path' => $this->logo_path]);
        Audit::site('settings.appearance_reset');

        return $this->redirectRoute('site.appearance');
    }

    public function render()
    {
        return view('livewire.admin.appearance', [
            'radii' => AppearanceService::RADII,
            'themes' => AppearanceService::THEMES,
        ]);
    }
}
