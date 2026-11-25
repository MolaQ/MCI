<?php

namespace App\Livewire\Site;

use App\Enums\Feature;
use App\Support\Audit;
use App\Support\Features as FeatureSwitches;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Witryna → Funkcje: master switches for the whole portal, and whether
 * each school may change a feature for itself.
 */
#[Title('Funkcje')]
class Features extends Component
{
    public function mount(): void
    {
        $this->authorize('site-admin');
    }

    public function toggle(string $key, string $field): void
    {
        $this->authorize('site-admin');
        abort_unless(in_array($field, ['on', 'override'], true), 422);

        $feature = Feature::from($key);
        $switches = app(FeatureSwitches::class);
        $state = $switches->site($feature);
        $state[$field] = ! $state[$field];
        $switches->setSite($feature, $state['on'], $state['override']);

        Audit::site('features.site_updated', null, ['feature' => $feature->value, $field => $state[$field]]);
        Flux::toast(text: $field === 'on'
            ? ($state['on'] ? __('Włączono: :f', ['f' => $feature->label()]) : __('Wyłączono w całym portalu: :f', ['f' => $feature->label()]))
            : ($state['override'] ? __('Szkoły mogą zmieniać: :f', ['f' => $feature->label()]) : __('Zablokowano dla szkół: :f', ['f' => $feature->label()])));
    }

    public function render()
    {
        $switches = app(FeatureSwitches::class);

        return view('livewire.site.features', [
            'groups' => Feature::byCategory(),
            'state' => collect(Feature::cases())->mapWithKeys(fn (Feature $f) => [$f->value => $switches->site($f)]),
            'parentOff' => collect(Feature::cases())->mapWithKeys(fn (Feature $f) => [$f->value => $f->parent() && ! $switches->enabled($f->parent(), null)]),
        ]);
    }
}
