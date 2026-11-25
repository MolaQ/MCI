<div class="space-y-6">
    <x-ui.page-header :title="__('Dziennik zdarzeń')" :subtitle="__('Kto i kiedy zmienił konta, uprawnienia, grupy i ustawienia.')" />

    <flux:select wire:model.live="action" class="max-w-xs">
        <flux:select.option value="">{{ __('Wszystkie zdarzenia') }}</flux:select.option>
        @foreach ($this->actions as $a)
            <flux:select.option :value="$a">{{ __('audit.'.$a) !== 'audit.'.$a ? __('audit.'.$a) : $a }}</flux:select.option>
        @endforeach
    </flux:select>

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
                    <flux:table.cell class="text-zinc-500">{{ $entry->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->actor?->name ?? __('System') }}</flux:table.cell>
                    <flux:table.cell>{{ __('audit.'.$entry->action) !== 'audit.'.$entry->action ? __('audit.'.$entry->action) : $entry->action }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($entry->subject instanceof \App\Models\User)
                            {{ $entry->subject->name }}
                        @elseif ($entry->subject instanceof \App\Models\Group)
                            {{ $entry->subject->name }}
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden max-w-md truncate font-mono text-xs text-zinc-500">
                        {{ $entry->properties ? json_encode($entry->properties, JSON_UNESCAPED_UNICODE) : '' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5"><x-ui.empty-state :title="__('Brak wpisów')" /></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
