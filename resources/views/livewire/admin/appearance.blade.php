<div class="space-y-6">
    <x-ui.page-header :title="__('Wygląd')" :subtitle="__('Nazwa szkoły, logo, kolory i zaokrąglenia — zmiany widzą wszyscy użytkownicy.')" />

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <flux:heading size="lg">{{ __('Identyfikacja') }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2"><flux:input wire:model="school_name" :label="__('Pełna nazwa')" /></div>
                    <flux:input wire:model="short_name" :label="__('Skrót')" />
                </div>

                <div class="flex flex-wrap items-end gap-4">
                    <flux:input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" :label="__('Logo (PNG/JPG/WebP, maks. 1 MB)')" class="max-w-sm" />
                    @if ($logo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo_path) }}" alt="" class="h-10 w-auto" />
                        <flux:button size="sm" variant="ghost" wire:click="removeLogo">{{ __('Usuń logo') }}</flux:button>
                    @endif
                </div>
            </flux:card>

            <flux:card class="space-y-5">
                <flux:heading size="lg">{{ __('Kolory i kształty') }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['primary_color' => __('Kolor główny'), 'primary_foreground' => __('Tekst na kolorze głównym'), 'danger_color' => __('Kolor alarmu')] as $field => $label)
                        <flux:field>
                            <flux:label>{{ $label }}</flux:label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live="{{ $field }}" class="h-10 w-14 cursor-pointer rounded-mci border border-zinc-200 bg-white p-1 dark:border-zinc-600 dark:bg-zinc-800" />
                                <flux:input wire:model.live="{{ $field }}" class="max-w-28" />
                            </div>
                            <flux:error :name="$field" />
                        </flux:field>
                    @endforeach
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:radio.group wire:model.live="radius" :label="__('Zaokrąglenie')" variant="segmented">
                        @foreach ($radii as $key => $value)
                            <flux:radio :value="$key" :label="__('radius.'.$key)" />
                        @endforeach
                    </flux:radio.group>

                    <flux:radio.group wire:model="default_theme" :label="__('Domyślny motyw')" variant="segmented">
                        <flux:radio value="system" :label="__('Systemowy')" />
                        <flux:radio value="light" :label="__('Jasny')" />
                        <flux:radio value="dark" :label="__('Ciemny')" />
                    </flux:radio.group>
                </div>
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Podgląd') }}</flux:heading>
                @php($r = $radii[$radius] ?? '0.5rem')
                <div class="space-y-3" style="--mci-radius: {{ $r }}">
                    <div class="flex items-center gap-2">
                        <span class="flex size-10 items-center justify-center text-sm font-bold" style="border-radius: {{ $r }}; background: {{ \App\Support\Appearance::sanitizeHex($primary_color, '#1d4ed8') }}; color: {{ \App\Support\Appearance::sanitizeHex($primary_foreground, '#ffffff') }}">{{ \Illuminate\Support\Str::limit($short_name, 4, '') }}</span>
                        <span class="font-semibold">{{ $school_name }}</span>
                    </div>
                    <button type="button" class="w-full px-4 py-2 text-sm font-medium" style="border-radius: {{ $r }}; background: {{ \App\Support\Appearance::sanitizeHex($primary_color, '#1d4ed8') }}; color: {{ \App\Support\Appearance::sanitizeHex($primary_foreground, '#ffffff') }}">{{ __('Przycisk główny') }}</button>
                    <div class="px-4 py-3 text-sm font-semibold text-white" style="border-radius: {{ $r }}; background: {{ \App\Support\Appearance::sanitizeHex($danger_color, '#dc2626') }}">{{ __('ALARM — Potwierdzam odbiór') }}</div>
                </div>
            </flux:card>

            <div class="flex flex-col gap-2">
                <flux:button type="submit" variant="primary">{{ __('Zapisz wygląd') }}</flux:button>
                <flux:button variant="ghost" wire:click="resetDefaults" wire:confirm="{{ __('Przywrócić domyślne kolory i nazwy?') }}">{{ __('Przywróć domyślne') }}</flux:button>
            </div>
        </div>
    </form>
</div>
