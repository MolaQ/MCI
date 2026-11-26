<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Models\School;
use App\Support\Audit;
use App\Support\CurrentSchool;
use App\Support\Settings;
use App\Support\TvScreens;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tryb TV: which screens the board rotates and for how many seconds each.
 *  - scope "site":   Witryna → Tryb TV (site admin), the default everywhere;
 *  - scope "school": Szkoła → Tryb TV (settings.manage), the school's own
 *    rotation, or "like the site".
 * A single TV can still override both with ?ekrany= in its link.
 */
#[Title('Ekrany TV')]
class TvSettings extends Component
{
    public string $scope = 'school';

    /** @var list<array{key: string, on: bool, seconds: int}> */
    public array $rows = [];

    /** School scope: follow the site's rotation. */
    public bool $inherit = true;

    public function mount(string $scope = 'school'): void
    {
        $this->scope = $scope === 'site' ? 'site' : 'school';
        $this->authorizeAccess();
        $tv = app(TvScreens::class);
        if ($this->scope === 'site') {
            $this->rows = $tv->site();
        } else {
            $own = $tv->ownOf($this->school());
            $this->inherit = $own === null;
            $this->rows = $own ?? $tv->site();
        }
    }

    private function school(): ?School
    {
        return $this->scope === 'site' ? null : app(CurrentSchool::class)->get();
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($this->scope === 'site'
            ? $user->can('site-admin')
            : app(CurrentSchool::class)->id() && $user->can(Permission::SettingsManage->value), 403);
    }

    public function move(int $index, int $by): void
    {
        $to = $index + ($by < 0 ? -1 : 1);
        if (isset($this->rows[$index], $this->rows[$to])) {
            [$this->rows[$index], $this->rows[$to]] = [$this->rows[$to], $this->rows[$index]];
        }
    }

    public function save(Settings $settings): void
    {
        $this->authorizeAccess();
        $min = (int) config('mci.tv.min_seconds', 5);
        $max = (int) config('mci.tv.max_seconds', 600);
        $this->validate([
            'rows' => ['array'],
            'rows.*.seconds' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
        ], [], ['rows.*.seconds' => __('czas wyświetlania')]);

        $rows = TvScreens::normalize($this->rows, TvScreens::defaults());
        if (! collect($rows)->contains('on', true)) {
            $this->addError('rows', __('Włącz co najmniej jeden ekran.'));

            return;
        }
        $this->rows = $rows;

        if ($this->scope === 'site') {
            $settings->set('tv', ['screens' => $rows]);
            Audit::site('settings.tv_updated', null, ['screens' => TvScreens::toQuery($rows)]);
        } else {
            $school = $this->school();
            $all = $school->settings ?? [];
            if ($this->inherit) {
                unset($all['tv']);
            } else {
                $all['tv'] = ['screens' => $rows];
            }
            $school->update(['settings' => $all]);
            Audit::log('school.tv_updated', $school, ['screens' => $this->inherit ? 'site' : TvScreens::toQuery($rows)], schoolId: $school->id);
        }

        Flux::toast(variant: 'success', text: __('Zapisano ustawienia trybu TV.'));
    }

    public function resetDefaults(): void
    {
        $this->rows = TvScreens::defaults();
    }

    public function render()
    {
        $school = $this->school();
        $effective = $this->scope === 'school' && $this->inherit ? app(TvScreens::class)->site() : TvScreens::normalize($this->rows, TvScreens::defaults());
        $slug = $school?->is_listed ? $school->slug : null;

        return view('livewire.admin.tv-settings', [
            'catalog' => TvScreens::catalog(),
            'min' => (int) config('mci.tv.min_seconds', 5),
            'max' => (int) config('mci.tv.max_seconds', 600),
            'cycle' => collect($effective)->where('on', true)->sum('seconds'),
            'boardUrl' => route('tv', array_filter(['szkola' => $slug])),
            'customUrl' => route('tv', array_filter(['szkola' => $slug, 'ekrany' => TvScreens::toQuery($effective)])),
            'schoolName' => $school?->short_name,
        ]);
    }
}
