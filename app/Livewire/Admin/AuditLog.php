<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Models\AuditLog as AuditLogModel;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Two journals over one table (docs/ARCHITECTURE.md §3.7):
 *  - scope "school": Logi szkoły — entries of the active school (audit.view)
 *  - scope "site":   Dziennik witryny — platform entries (site admins)
 */
class AuditLog extends Component
{
    use WithPagination;

    #[Locked]
    public string $scope = 'school';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $period = '';

    #[Url(except: '')]
    public string $search = '';

    public const PERIODS = ['today' => 'Dziś', '7d' => '7 dni', '30d' => '30 dni', 'month' => 'Ten miesiąc'];

    public function mount(string $scope = 'school'): void
    {
        $this->scope = $scope === 'site' ? 'site' : 'school';
        $this->authorizeScope();
    }

    private function authorizeScope(): void
    {
        if ($this->scope === 'site') {
            $this->authorize('site-admin');
        } else {
            abort_unless(app(CurrentSchool::class)->id() && Auth::user()->can(Permission::AuditView->value), 403);
        }
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function setCategory(string $category): void
    {
        $this->category = $this->category === $category ? '' : $category;
        $this->resetPage();
    }

    public function setPeriod(string $period): void
    {
        $this->period = $this->period === $period ? '' : $period;
        $this->resetPage();
    }

    private function query(): Builder
    {
        return AuditLogModel::query()
            ->when($this->scope === 'site',
                fn ($q) => $q->whereNull('school_id'),
                fn ($q) => $q->where('school_id', app(CurrentSchool::class)->id()))
            ->when($this->category !== '', fn ($q) => $q->where('action', 'like', $this->category.'.%'))
            ->when($this->period !== '', fn ($q) => $q->where('created_at', '>=', match ($this->period) {
                'today' => now()->startOfDay(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                default => now()->startOfMonth(),
            }))
            ->when(trim($this->search) !== '', function ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->search)).'%';
                $q->where(fn ($w) => $w
                    ->where('action', 'like', $like)
                    ->orWhere('properties', 'like', $like)
                    ->orWhereHas('actor', fn ($a) => $a->search($this->search)));
            });
    }

    #[Computed]
    public function entries()
    {
        return $this->query()
            ->with(['actor', 'subject' => fn ($morph) => $morph->withoutGlobalScopes()])
            ->latest('id')
            ->paginate(50);
    }

    /**
     * @return array<string, int> category => count (for the filter pills)
     */
    #[Computed]
    public function categories(): array
    {
        return AuditLogModel::query()
            ->when($this->scope === 'site', fn ($q) => $q->whereNull('school_id'), fn ($q) => $q->where('school_id', app(CurrentSchool::class)->id()))
            ->pluck('action')
            ->map(fn ($a) => strtok($a, '.'))
            ->countBy()
            ->sortKeys()
            ->all();
    }

    public function export(): StreamedResponse
    {
        $name = ($this->scope === 'site' ? 'dziennik-witryny' : 'logi-szkoly').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['data', 'kto', 'zdarzenie', 'kod', 'obiekt', 'szczegóły', 'ip'], ';');

            $this->query()->with('actor')->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $e) {
                    fputcsv($out, [
                        $e->created_at->format('Y-m-d H:i:s'),
                        $e->actor?->name ?? 'System',
                        static::label($e->action),
                        $e->action,
                        $e->subject_type ? $e->subject_type.'#'.$e->subject_id : '',
                        $e->properties ? json_encode($e->properties, JSON_UNESCAPED_UNICODE) : '',
                        $e->ip,
                    ], ';');
                }
            });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function label(string $action): string
    {
        $key = 'audit.'.$action;

        return __($key) !== $key ? __($key) : $action;
    }

    public function render()
    {
        $this->authorizeScope();

        return view('livewire.admin.audit-log')
            ->title($this->scope === 'site' ? __('Dziennik witryny') : __('Logi szkoły'));
    }
}
