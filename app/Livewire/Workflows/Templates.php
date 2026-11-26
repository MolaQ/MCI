<?php

namespace App\Livewire\Workflows;

use App\Domain\Workflows\Actions\ManageTemplates;
use App\Models\School;
use App\Models\WorkflowTemplate;
use App\Support\CurrentSchool;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Szablony obiegów: scope "site" (Witryna, system-wide templates) or
 * "school" (Administracja, the school's own templates; the system-wide ones
 * are listed read-only for reference).
 */
#[Title('Szablony obiegów')]
class Templates extends Component
{
    public string $scope = 'school';

    public function mount(?string $scope = 'school'): void
    {
        $this->scope = $scope === 'site' ? 'site' : 'school';
        abort_if($this->scope === 'school' && ! app(CurrentSchool::class)->id(), 404);
        app(ManageTemplates::class)->authorize(Auth::user(), $this->school());
    }

    private function school(): ?School
    {
        return $this->scope === 'site' ? null : app(CurrentSchool::class)->get();
    }

    #[Computed]
    public function templates()
    {
        return WorkflowTemplate::query()->where('school_id', $this->school()?->id)
            ->withCount(['stages', 'requests', 'requests as open_count' => fn ($q) => $q->whereIn('status', ['in_progress', 'returned'])])
            ->orderBy('position')->orderBy('id')->get();
    }

    #[Computed]
    public function systemTemplates()
    {
        return $this->scope === 'school'
            ? WorkflowTemplate::query()->whereNull('school_id')->where('is_active', true)->withCount('stages')->orderBy('position')->get()
            : collect();
    }

    private function find(int $id): WorkflowTemplate
    {
        return WorkflowTemplate::query()->where('school_id', $this->school()?->id)->findOrFail($id);
    }

    public function move(int $id, int $direction, ManageTemplates $manage): void
    {
        $manage->move(Auth::user(), $this->find($id), $direction);
        unset($this->templates);
    }

    public function toggle(int $id, ManageTemplates $manage): void
    {
        $t = $this->find($id);
        $manage->setActive(Auth::user(), $t, ! $t->is_active);
        unset($this->templates);
        Flux::toast(text: $t->is_active ? __('Szablon jest znów dostępny.') : __('Szablon wyłączony — nowe wnioski nie są możliwe.'));
    }

    public function delete(int $id, ManageTemplates $manage): void
    {
        $manage->delete(Auth::user(), $this->find($id));
        unset($this->templates);
        Flux::toast(text: __('Usunięto szablon. Złożone wnioski zostają bez zmian.'));
    }

    public function render()
    {
        return view('livewire.workflows.templates', [
            'editRoute' => fn (?WorkflowTemplate $t = null) => $this->scope === 'site'
                ? ($t ? route('site.workflows.edit', $t) : route('site.workflows.create'))
                : ($t ? route('admin.workflows.edit', $t) : route('admin.workflows.create')),
        ]);
    }
}
