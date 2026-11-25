<div class="space-y-6">
    <x-ui.page-header :title="__('Funkcje')" :subtitle="__('Główne przełączniki całego portalu. Wyłączona tutaj funkcja znika we wszystkich szkołach; „Szkoły mogą zmieniać” pozwala szkole wyłączyć ją u siebie.')" />

    @foreach ($groups as $category => $features)
        <flux:card class="space-y-1">
            <flux:heading size="lg" class="mb-2">{{ __('features.categories.'.$category) }}</flux:heading>
            @foreach ($features as $feature)
                @php($s = $state[$feature->value])
                <div wire:key="f-{{ $feature->value }}" data-feature="{{ $feature->value }}" class="flex flex-col gap-3 border-t border-zinc-100 py-3 first-of-type:border-t-0 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700/60 {{ $feature->parent() ? 'sm:ps-6' : '' }}">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 font-medium text-zinc-900 dark:text-white">
                            @if ($feature->parent())<flux:icon.arrow-turn-down-right variant="micro" class="text-zinc-400" />@endif
                            {{ $feature->label() }}
                            @if ($parentOff[$feature->value])
                                <flux:badge size="sm" color="zinc">{{ __('moduł wyłączony') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text size="sm">{{ $feature->description() }}</flux:text>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <x-ui.pill wire:click="toggle('{{ $feature->value }}', 'on')" :active="$s['on']">
                            <flux:icon :name="$s['on'] ? 'check-circle' : 'x-circle'" variant="micro" /> {{ $s['on'] ? __('Włączona') : __('Wyłączona') }}
                        </x-ui.pill>
                        <x-ui.pill wire:click="toggle('{{ $feature->value }}', 'override')" :active="$s['override']" :disabled="! $s['on']">
                            <flux:icon :name="$s['override'] ? 'lock-open' : 'lock-closed'" variant="micro" /> {{ $s['override'] ? __('Szkoły mogą zmieniać') : __('Zablokowana dla szkół') }}
                        </x-ui.pill>
                    </div>
                </div>
            @endforeach
        </flux:card>
    @endforeach
</div>
