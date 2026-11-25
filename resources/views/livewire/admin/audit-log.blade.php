<div class="space-y-6">
    <x-ui.page-header
        :title="$scope === 'site' ? __('Dziennik witryny') : __('Logi szkoły')"
        :subtitle="$scope === 'site'
            ? __('Decyzje dotyczące całej platformy: szkoły, aktualizacje, kopie zapasowe, wygląd.')
            : __('Kto i kiedy zmienił konta, uprawnienia, grupy, zaproszenia i ustawienia w tej szkole.')">
        <x-slot:actions>
            <flux:button icon="arrow-down-tray" wire:click="export">{{ __('Eksport CSV') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            @foreach ($this->categories as $cat => $count)
                <x-ui.pill :active="$category === $cat" wire:click="setCategory('{{ $cat }}')" :count="$count">
                    {{ __('audit_categories.'.$cat) !== 'audit_categories.'.$cat ? __('audit_categories.'.$cat) : $cat }}
                </x-ui.pill>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @foreach (\App\Livewire\Admin\AuditLog::PERIODS as $key => $label)
                <x-ui.pill :active="$period === $key" wire:click="setPeriod('{{ $key }}')">{{ __($label) }}</x-ui.pill>
            @endforeach
            <flux:input wire:model.live.debounce.300ms="search" size="sm" icon="magnifying-glass" :placeholder="__('Szukaj: osoba, zdarzenie, szczegóły')" class="ms-auto max-w-72" />
        </div>
    </div>

    <flux:table :paginate="$this->entries">
        <flux:table.columns>
            <flux:table.column>{{ __('Kiedy') }}</flux:table.column>
            <flux:table.column>{{ __('Kto') }}</flux:table.column>
            <flux:table.column>{{ __('Zdarzenie') }}</flux:table.column>
            <flux:table.column>{{ __('Dotyczy') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Szczegóły') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->entries as $entry)
                <flux:table.row :key="$entry->id">
                    <flux:table.cell class="text-zinc-500 tabular-nums">{{ $entry->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->actor?->name ?? __('System') }}</flux:table.cell>
                    <flux:table.cell>{{ \App\Livewire\Admin\AuditLog::label($entry->action) }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->subject?->name ?? ($entry->subject?->email ?? '—') }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden max-w-md truncate font-mono text-xs text-zinc-500">
                        {{ $entry->properties ? json_encode($entry->properties, JSON_UNESCAPED_UNICODE) : '' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5"><x-ui.empty-state icon="clipboard-document-list" :title="__('Brak wpisów')" /></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
