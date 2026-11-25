<div class="space-y-6">
    <x-ui.page-header :title="__('Funkcje szkoły')" :subtitle="__('Włącz lub wyłącz funkcje w szkole :name. Zmiany obowiązują od razu i trafiają do logów szkoły.', ['name' => $school->short_name])" />

    @foreach ($groups as $category => $features)
        <flux:card class="space-y-1">
            <flux:heading size="lg" class="mb-2">{{ __('features.categories.'.$category) }}</flux:heading>
            @foreach ($features as $feature)
                @php($r = $rows[$feature->value])
                @php($locked = ! $r['site']['on'] || ! $r['site']['override'])
                <div wire:key="sf-{{ $feature->value }}" data-feature="{{ $feature->value }}" class="flex flex-col gap-3 border-t border-zinc-100 py-3 first-of-type:border-t-0 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700/60 {{ $feature->parent() ? 'sm:ps-6' : '' }}">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 font-medium text-zinc-900 dark:text-white">
                            @if ($feature->parent())<flux:icon.arrow-turn-down-right variant="micro" class="text-zinc-400" />@endif
                            {{ $feature->label() }}
                            @if (! $r['site']['on'])
                                <flux:badge size="sm" color="zinc" icon="lock-closed">{{ __('wyłączona przez administratora witryny') }}</flux:badge>
                            @elseif (! $r['site']['override'])
                                <flux:badge size="sm" color="zinc" icon="lock-closed">{{ __('ustawiona przez administratora witryny') }}</flux:badge>
                            @elseif ($r['parentOff'])
                                <flux:badge size="sm" color="amber">{{ __('najpierw włącz moduł nadrzędny') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text size="sm">{{ $feature->description() }}</flux:text>
                    </div>
                    <div class="shrink-0">
                        @if ($locked)
                            <span class="inline-flex items-center gap-1 text-sm text-zinc-500">
                                <flux:icon :name="$r['effective'] ? 'check-circle' : 'x-circle'" variant="micro" /> {{ $r['effective'] ? __('Włączona') : __('Wyłączona') }}
                            </span>
                        @else
                            <flux:switch wire:click="toggle('{{ $feature->value }}')" :checked="$r['own']" :aria-label="$feature->label()" />
                        @endif
                    </div>
                </div>
            @endforeach
        </flux:card>
    @endforeach
</div>
