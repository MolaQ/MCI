<div class="space-y-6">
    <x-ui.page-header :title="__('Ekrany TV')"
        :subtitle="$scope === 'site' ? __('Ekrany tablicy TV i czas wyświetlania każdego z nich. Domyślne dla wszystkich szkół.') : __('Ekrany tablicy TV szkoły :school i czas wyświetlania każdego z nich.', ['school' => $schoolName])">
        <x-slot:actions>
            @feature('public.tv')
                <flux:button :href="$boardUrl" target="_blank" icon="tv" size="sm">{{ __('Otwórz tablicę') }}</flux:button>
            @endfeature
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <form wire:submit="save" class="space-y-5 xl:col-span-2" data-tv-settings>
            @if ($scope === 'school')
                <flux:card>
                    <flux:switch wire:model.live="inherit" :label="__('Jak w całej witrynie')" :description="__('Wyłącz, aby ustawić własną kolejność i czasy ekranów dla tej szkoły.')" />
                </flux:card>
            @endif

            <flux:card class="space-y-1">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Ekrany w kolejności wyświetlania') }}</flux:heading>
                    <span class="text-sm text-zinc-500">{{ __('Pełny cykl: :s s', ['s' => $cycle]) }}</span>
                </div>
                @php($locked = $scope === 'school' && $inherit)
                <ol @class(['divide-y divide-zinc-100 dark:divide-zinc-800', 'pointer-events-none opacity-50' => $locked])>
                    @foreach ($rows as $i => $row)
                        @php($meta = $catalog[$row['key']])
                        <li wire:key="tv-{{ $row['key'] }}" class="flex flex-wrap items-center gap-3 py-3 sm:flex-nowrap" data-tv-row="{{ $row['key'] }}">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-mci bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon :name="$meta['icon']" variant="mini" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $meta['label'] }}</div>
                                <div class="text-sm text-zinc-500">{{ $meta['hint'] }}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-28">
                                    <flux:input.group>
                                        <flux:input type="number" wire:model="rows.{{ $i }}.seconds" min="{{ $min }}" max="{{ $max }}" size="sm" :aria-label="__('Sekundy: :screen', ['screen' => $meta['label']])" :disabled="$locked" />
                                        <flux:input.group.suffix>s</flux:input.group.suffix>
                                    </flux:input.group>
                                </div>
                                <flux:switch wire:model.live="rows.{{ $i }}.on" :aria-label="__('Pokazuj: :screen', ['screen' => $meta['label']])" :disabled="$locked" />
                                <div class="flex flex-col">
                                    <button type="button" wire:click="move({{ $i }}, -1)" @disabled($i === 0 || $locked) class="rounded p-0.5 text-zinc-400 hover:text-zinc-700 disabled:opacity-30" title="{{ __('Wyżej') }}"><flux:icon.chevron-up variant="micro" /></button>
                                    <button type="button" wire:click="move({{ $i }}, 1)" @disabled($i === count($rows) - 1 || $locked) class="rounded p-0.5 text-zinc-400 hover:text-zinc-700 disabled:opacity-30" title="{{ __('Niżej') }}"><flux:icon.chevron-down variant="micro" /></button>
                                </div>
                            </div>
                            @error('rows.'.$i.'.seconds')<p class="basis-full text-sm text-red-600">{{ $message }}</p>@enderror
                        </li>
                    @endforeach
                </ol>
                <flux:error name="rows" />
            </flux:card>

            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" variant="primary" icon="check">{{ __('Zapisz') }}</flux:button>
                @unless ($scope === 'school' && $inherit)
                    <flux:button type="button" variant="ghost" wire:click="resetDefaults">{{ __('Przywróć domyślne') }}</flux:button>
                @endunless
            </div>
        </form>

        <flux:card class="space-y-4 text-sm">
            <flux:heading size="lg">{{ __('Jak to działa') }}</flux:heading>
            <ul class="list-disc space-y-2 ps-5 text-zinc-600 dark:text-zinc-300">
                <li>{{ __('Tablica pokazuje ekrany po kolei, każdy przez podaną liczbę sekund (:min–:max s). Ekran bez treści (np. brak projektów publicznych) jest pomijany.', ['min' => $min, 'max' => $max]) }}</li>
                <li>{{ __('Dane odświeżają się same; na tablicy działa strzałka ← →, spacja (pauza), F (pełny ekran) i Esc.') }}</li>
                <li>{{ __('Pilny komunikat publiczny jest widoczny na każdym ekranie.') }}</li>
                <li>{{ __('Kolejność: ustawienia witryny → własne ustawienia szkoły → link konkretnego telewizora.') }}</li>
            </ul>
            <div class="space-y-1.5">
                <flux:heading size="sm">{{ __('Link dla konkretnego telewizora') }}</flux:heading>
                <flux:text size="sm">{{ __('Ten link zapamiętuje obecny zestaw ekranów; możesz go zmienić ręcznie, np. ekrany=agenda:30,calendar:20.') }}</flux:text>
                <flux:input readonly :value="$customUrl" copyable size="sm" data-tv-link />
            </div>
        </flux:card>
    </div>
</div>
