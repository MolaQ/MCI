@props([
    'model',
    'label' => null,
    'current' => null,
    'aspect' => 'free',
    'presets' => ['free', '1:1', '4:3', '16:9', '3:1'],
    'maxSize' => 1600,
    'remove' => null,
    'hint' => null,
    'background' => true,
    'gallery' => false,
    'button' => null,
])

@php
    $background = $background && app(\App\Support\Features::class)->enabled(\App\Enums\Feature::BackgroundRemoval);
    $presetLabels = ['free' => __('Dowolne'), '1:1' => '1:1', '4:3' => '4:3', '3:2' => '3:2', '16:9' => '16:9', '3:1' => '3:1', '4:1' => '4:1'];
@endphp

{{-- Shared image picker + editor (docs/ARCHITECTURE.md §12.5). --}}
<div x-data="mciImageEditor(@js(['model' => $model, 'aspect' => $aspect, 'presets' => $presets, 'maxSize' => $maxSize, 'background' => $background, 'gallery' => $gallery]))"
     {{ $attributes->class('space-y-2') }}>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    @if ($gallery)
        <flux:button size="sm" icon="photo" x-on:click="choose()">{{ $button ?? __('Dodaj obraz') }}</flux:button>
    @else
        <div class="flex flex-wrap items-center gap-4">
        <div class="mci-checker flex h-20 w-36 shrink-0 items-center justify-center overflow-hidden rounded-mci border border-zinc-200 dark:border-zinc-700">
            <template x-if="resultUrl">
                <img :src="resultUrl" alt="" class="max-h-full max-w-full object-contain" />
            </template>
            <template x-if="!resultUrl">
                @if ($current)
                    <img src="{{ $current }}" alt="" class="max-h-full max-w-full object-contain" />
                @else
                    <flux:icon.photo class="size-7 text-zinc-400" />
                @endif
            </template>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" icon="arrow-up-tray" x-on:click="choose()">
                {{ $current ? __('Zmień obraz') : __('Wybierz obraz') }}
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" x-show="resultUrl" x-cloak x-on:click="discard()">{{ __('Cofnij') }}</flux:button>
            @if ($remove && $current)
                <flux:button size="sm" variant="ghost" icon="trash" x-show="!resultUrl" wire:click="{{ $remove }}"
                             wire:confirm="{{ __('Usunąć obraz?') }}">{{ __('Usuń') }}</flux:button>
            @endif
        </div>
        </div>

    @endif

    <p x-show="resultUrl" x-cloak class="text-xs text-amber-700 dark:text-amber-400">{{ __('Obraz gotowy — zapisz formularz, aby go zastosować.') }}</p>
    @if ($hint)
        <flux:description>{{ $hint }}</flux:description>
    @endif
    <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600"></p>
    <flux:error :name="$model" />

    <input type="file" x-ref="file" class="hidden" accept="image/png,image/jpeg,image/webp,image/gif" x-on:change="load($event)" />

    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
             x-on:keydown.escape.window="open && close()"
             class="fixed inset-0 z-[60] flex flex-col bg-zinc-950/95 text-zinc-100" role="dialog" aria-modal="true" aria-label="{{ __('Edycja obrazu') }}">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                <div class="flex items-center gap-2 font-semibold"><flux:icon.scissors class="size-5" /> {{ __('Edytuj obraz') }}</div>
                <button type="button" x-on:click="close()" class="rounded-mci p-2 hover:bg-white/10" aria-label="{{ __('Zamknij') }}"><flux:icon.x-mark class="size-5" /></button>
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto lg:flex-row lg:overflow-hidden">
                <div x-ref="stage" class="relative flex h-[45vh] shrink-0 items-center justify-center p-2 lg:h-auto lg:flex-1">
                    <canvas x-ref="preview"
                            class="mci-checker touch-none rounded-sm shadow-2xl ring-1 ring-white/20"
                            :class="picking ? 'cursor-crosshair' : 'cursor-grab active:cursor-grabbing'"
                            x-on:pointerdown="pointerDown($event)" x-on:pointermove="pointerMove($event)"
                            x-on:pointerup="pointerUp()" x-on:pointercancel="pointerUp()"
                            x-on:wheel.prevent="wheel($event)"
                            x-on:touchstart="touchStart($event)" x-on:touchmove.prevent="touchMove($event)" x-on:touchend="touchEnd()"></canvas>
                    <p x-show="picking" class="absolute top-3 rounded-full bg-black/70 px-3 py-1 text-xs">{{ __('Kliknij kolor tła na obrazie') }}</p>
                </div>

                <div class="w-full shrink-0 space-y-5 border-t border-white/10 p-4 text-sm lg:w-80 lg:overflow-y-auto lg:border-t-0 lg:border-l">
                    <section class="space-y-2">
                        <h3 class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">{{ __('Kadr') }}</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($presets as $preset)
                                <button type="button" x-on:click="aspect = @js($preset)"
                                        :class="aspect === @js($preset) ? 'bg-white text-zinc-900' : 'bg-white/10 hover:bg-white/20'"
                                        class="rounded-full px-3 py-1 text-xs font-medium transition">{{ $presetLabels[$preset] ?? $preset }}</button>
                            @endforeach
                        </div>
                    </section>

                    <section class="space-y-2">
                        <h3 class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">{{ __('Powiększenie i położenie') }}</h3>
                        <div class="flex items-center gap-2">
                            <button type="button" x-on:click="setZoom(zoom / 1.15)" class="rounded-mci p-1.5 hover:bg-white/10" aria-label="{{ __('Pomniejsz') }}"><flux:icon.magnifying-glass-minus class="size-5" /></button>
                            <input type="range" min="0.2" max="8" step="0.01" x-model.number="zoom" class="w-full accent-white" aria-label="{{ __('Powiększenie') }}" />
                            <button type="button" x-on:click="setZoom(zoom * 1.15)" class="rounded-mci p-1.5 hover:bg-white/10" aria-label="{{ __('Powiększ') }}"><flux:icon.magnifying-glass-plus class="size-5" /></button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" x-on:click="zoom = 1; offsetX = 0; offsetY = 0" class="rounded-full bg-white/10 px-3 py-1 text-xs hover:bg-white/20">{{ __('Cały obraz') }}</button>
                            <button type="button" x-on:click="fill()" class="rounded-full bg-white/10 px-3 py-1 text-xs hover:bg-white/20">{{ __('Wypełnij kadr') }}</button>
                            <button type="button" x-on:click="offsetX = 0; offsetY = 0" class="rounded-full bg-white/10 px-3 py-1 text-xs hover:bg-white/20">{{ __('Wyśrodkuj') }}</button>
                        </div>
                        <p class="text-xs text-zinc-400">{{ __('Przeciągnij obraz, aby go przesunąć. Kółko myszy lub dwa palce — powiększenie.') }}</p>
                    </section>

                    <section class="space-y-2">
                        <h3 class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">{{ __('Obrót i odbicie') }}</h3>
                        <div class="flex gap-1.5">
                            <button type="button" x-on:click="rotate(-90)" class="rounded-mci bg-white/10 p-2 hover:bg-white/20" title="{{ __('Obróć w lewo') }}"><flux:icon.arrow-uturn-left class="size-5" /></button>
                            <button type="button" x-on:click="rotate(90)" class="rounded-mci bg-white/10 p-2 hover:bg-white/20" title="{{ __('Obróć w prawo') }}"><flux:icon.arrow-uturn-right class="size-5" /></button>
                            <button type="button" x-on:click="flip('x')" :class="flipX && 'ring-2 ring-white'" class="rounded-mci bg-white/10 p-2 hover:bg-white/20" title="{{ __('Odbij w poziomie') }}"><flux:icon.arrows-right-left class="size-5" /></button>
                            <button type="button" x-on:click="flip('y')" :class="flipY && 'ring-2 ring-white'" class="rounded-mci bg-white/10 p-2 hover:bg-white/20" title="{{ __('Odbij w pionie') }}"><flux:icon.arrows-up-down class="size-5" /></button>
                        </div>
                    </section>

                    @if ($background)
                        <section class="space-y-2">
                            <h3 class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">{{ __('Usuwanie tła') }}</h3>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach (['off' => __('Bez zmian'), 'edges' => __('Tło od krawędzi'), 'all' => __('Cały kolor')] as $mode => $text)
                                    <button type="button" x-on:click="bgMode = @js($mode)"
                                            :class="bgMode === @js($mode) ? 'bg-white text-zinc-900' : 'bg-white/10 hover:bg-white/20'"
                                            class="rounded-full px-3 py-1 text-xs font-medium transition">{{ $text }}</button>
                                @endforeach
                            </div>
                            <div x-show="bgMode !== 'off'" x-cloak class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="bgColor" class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent" aria-label="{{ __('Kolor tła') }}" />
                                    <button type="button" x-on:click="picking = true" class="flex items-center gap-1 rounded-full bg-white/10 px-3 py-1 text-xs hover:bg-white/20"><flux:icon.eye-dropper class="size-4" /> {{ __('Pobierz z obrazu') }}</button>
                                    <button type="button" x-on:click="bgColor = '#ffffff'" class="rounded-full bg-white/10 px-3 py-1 text-xs hover:bg-white/20">{{ __('Białe') }}</button>
                                </div>
                                <label class="block space-y-1">
                                    <span class="flex justify-between text-xs text-zinc-300">{{ __('Tolerancja') }} <span x-text="tolerance + '%'"></span></span>
                                    <input type="range" min="0" max="60" x-model.number="tolerance" class="w-full accent-white" />
                                </label>
                                <label class="block space-y-1">
                                    <span class="flex justify-between text-xs text-zinc-300">{{ __('Wygładzenie krawędzi') }} <span x-text="softness + '%'"></span></span>
                                    <input type="range" min="0" max="30" x-model.number="softness" class="w-full accent-white" />
                                </label>
                                <p class="text-xs text-zinc-400">{{ __('„Tło od krawędzi” usuwa tylko kolor połączony z brzegiem obrazu, więc białe elementy wewnątrz logo zostają. Sprawdź wynik na szachownicy — przy cieniach i gradientach lepszy będzie plik PNG z przezroczystością od grafika.') }}</p>
                            </div>
                        </section>
                    @endif

                    <section class="space-y-2">
                        <h3 class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">{{ __('Format') }}</h3>
                        <div class="flex gap-1.5">
                            @foreach (['webp' => 'WebP', 'png' => 'PNG'] as $fmt => $text)
                                <button type="button" x-on:click="format = @js($fmt)"
                                        :class="format === @js($fmt) ? 'bg-white text-zinc-900' : 'bg-white/10 hover:bg-white/20'"
                                        class="rounded-full px-3 py-1 text-xs font-medium transition">{{ $text }}</button>
                            @endforeach
                        </div>
                        <p class="text-xs text-zinc-400">{{ __('Oba formaty zachowują przezroczystość. Obraz zostanie zmniejszony do maks. :px px.', ['px' => $maxSize]) }}</p>
                    </section>
                </div>
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-white/10 px-4 py-3">
                <button type="button" x-on:click="reset()" class="flex items-center gap-1 rounded-mci px-3 py-2 text-sm hover:bg-white/10"><flux:icon.arrow-path class="size-4" /> {{ __('Resetuj') }}</button>
                <div class="flex items-center gap-2">
                    <span x-show="busy" x-text="progress + '%'" class="text-xs text-zinc-400"></span>
                    <button type="button" x-on:click="close()" class="rounded-mci px-3 py-2 text-sm hover:bg-white/10">{{ __('Anuluj') }}</button>
                    <button type="button" x-on:click="apply()" :disabled="busy"
                            class="flex items-center gap-1 rounded-mci bg-white px-4 py-2 text-sm font-semibold text-zinc-900 hover:bg-zinc-200 disabled:opacity-60"><flux:icon.check class="size-4" /> {{ __('Zastosuj') }}</button>
                </div>
            </div>
        </div>
    </template>
</div>
