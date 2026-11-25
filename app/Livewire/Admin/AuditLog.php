<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog as AuditLogModel;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Dziennik zdarzeń')]
class AuditLog extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function entries()
    {
        return AuditLogModel::query()
            ->with(['actor', 'subject' => fn ($morph) => $morph->withoutGlobalScopes()])
            ->when($this->action !== '', fn ($q) => $q->where('action', 'like', $this->action.'%'))
            ->latest('id')
            ->paginate(50);
    }

    #[Computed]
    public function actions(): array
    {
        return AuditLogModel::query()->distinct()->orderBy('action')->pluck('action')->all();
    }

    public function render()
    {
        return view('livewire.admin.audit-log');
    }
}
