<?php

namespace App\Livewire\Admin;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Support\Audit;
use App\Support\CurrentSchool;
use App\Support\Features;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Administracja → Funkcje szkoły: the active school's own switches, within
 * what the site admin allows (features switched off or locked by the site
 * are shown read-only with the reason).
 */
#[Title('Funkcje szkoły')]
class SchoolFeatures extends Component
{
    public function mount(): void
    {
        $this->authorizeAccess();
    }

    private function authorizeAccess(): void
    {
        abort_unless(app(CurrentSchool::class)->id() && Auth::user()->can(Permission::SettingsManage->value), 403);
    }

    public function toggle(string $key): void
    {
        $this->authorizeAccess();
        $feature = Feature::from($key);
        $switches = app(Features::class);
        $site = $switches->site($feature);

        if (! $site['on'] || ! $site['override']) {
            Flux::toast(variant: 'danger', text: __('Tę funkcję ustawia administrator witryny.'));

            return;
        }

        $school = app(CurrentSchool::class)->required();
        $value = ! ($switches->schoolValue($school, $feature) ?? true);
        // "On" is the default, so it is stored as "no override".
        $switches->setForSchool($school, $feature, $value ? null : false);

        Audit::log('school.feature_updated', $school, ['feature' => $feature->value, 'on' => $value], schoolId: $school->id);
        Flux::toast(text: $value ? __('Włączono: :f', ['f' => $feature->label()]) : __('Wyłączono: :f', ['f' => $feature->label()]));
    }

    public function render()
    {
        $this->authorizeAccess();
        $school = app(CurrentSchool::class)->required();
        $switches = app(Features::class);

        return view('livewire.admin.school-features', [
            'school' => $school,
            'groups' => Feature::byCategory(),
            'rows' => collect(Feature::cases())->mapWithKeys(function (Feature $f) use ($switches, $school) {
                $site = $switches->site($f);

                return [$f->value => [
                    'site' => $site,
                    'own' => $switches->schoolValue($school, $f) ?? true,
                    'effective' => $switches->enabled($f, $school),
                    'parentOff' => $f->parent() && ! $switches->enabled($f->parent(), $school),
                ]];
            }),
        ]);
    }
}
