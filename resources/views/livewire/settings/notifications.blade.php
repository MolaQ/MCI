<div class="space-y-6">
    <x-ui.page-header :title="__('Powiadomienia i aplikacja')" :subtitle="__('Zainstaluj :app na telefonie i zdecyduj, co może Cię powiadomić.', ['app' => $appearance->shortName()])" />

    <div class="grid gap-6 lg:grid-cols-2">
        <div x-data="mciPush({ vapidKey: @js($pushEnabled ? $vapidKey : null) })">
        <flux:card class="h-full space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('To urządzenie') }}</flux:heading>
                    <flux:text>{{ __('Powiadomienia push docierają także po zamknięciu aplikacji.') }}</flux:text>
                </div>
                <flux:icon.device-phone-mobile class="size-8 shrink-0 text-zinc-400" />
            </div>

            {{-- Status, one of: off / ios-install / unsupported / denied / ready / on --}}
            <template x-if="state === 'on'">
                <div class="flex items-center gap-2 rounded-mci bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950/40 dark:text-green-300">
                    <flux:icon.check-circle variant="mini" /> {{ __('Powiadomienia są włączone na tym urządzeniu.') }}
                </div>
            </template>
            <template x-if="state === 'ready'">
                <div class="rounded-mci bg-zinc-50 p-3 text-sm dark:bg-zinc-800">{{ __('Powiadomienia są wyłączone na tym urządzeniu.') }}</div>
            </template>
            <template x-if="state === 'denied'">
                <div class="space-y-1 rounded-mci bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <p class="font-medium">{{ __('Przeglądarka blokuje powiadomienia dla tej strony.') }}</p>
                    <p>{{ __('Kliknij ikonę kłódki obok adresu strony → Uprawnienia → Powiadomienia → Zezwalaj, a potem odśwież stronę.') }}</p>
                </div>
            </template>
            <template x-if="state === 'unsupported'">
                <div class="rounded-mci bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">{{ __('Ta przeglądarka nie obsługuje powiadomień push. Użyj Chrome, Edge, Firefox lub Safari (iOS 16.4+).') }}</div>
            </template>
            <template x-if="state === 'ios-install'">
                <div class="space-y-2 rounded-mci bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                    <p class="font-medium">{{ __('Na iPhonie powiadomienia działają po dodaniu :app do ekranu początkowego:', ['app' => $appearance->shortName()]) }}</p>
                    <ol class="list-decimal space-y-1 ps-5">
                        <li>{{ __('Otwórz tę stronę w Safari.') }}</li>
                        <li>{{ __('Stuknij „Udostępnij” (kwadrat ze strzałką).') }}</li>
                        <li>{{ __('Wybierz „Do ekranu początkowego” i „Dodaj”.') }}</li>
                        <li>{{ __('Otwórz :app z ikony na ekranie i wróć tutaj.', ['app' => $appearance->shortName()]) }}</li>
                    </ol>
                    <p class="text-xs opacity-80">{{ __('Wymaga iOS 16.4 lub nowszego.') }}</p>
                </div>
            </template>
            <template x-if="state === 'off'">
                <div class="rounded-mci bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                    @if (! $pushConfigured)
                        {{ __('Powiadomienia push nie są jeszcze skonfigurowane na serwerze (klucze VAPID). Poproś administratora witryny.') }}
                    @else
                        {{ __('Powiadomienia push są wyłączone w Twojej szkole.') }}
                    @endif
                </div>
            </template>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="primary" icon="bell-alert" x-show="state === 'ready' || state === 'denied'" x-on:click="enable()" x-bind:disabled="busy">{{ __('Włącz powiadomienia') }}</flux:button>
                <flux:button icon="paper-airplane" x-show="state === 'on'" wire:click="sendTest">{{ __('Wyślij test') }}</flux:button>
                <flux:button variant="ghost" icon="bell-slash" x-show="state === 'on'" x-on:click="disable()" x-bind:disabled="busy">{{ __('Wyłącz na tym urządzeniu') }}</flux:button>
                <flux:button icon="arrow-down-tray" x-show="installable" x-cloak x-on:click="install()">{{ __('Zainstaluj aplikację') }}</flux:button>
            </div>
            <p x-show="message" x-text="message" x-cloak class="text-sm text-red-600"></p>

            <flux:text size="sm">{{ __('Tryb „Nie przeszkadzać” i wyciszenie telefonu mogą zatrzymać dźwięk powiadomienia — to ustawienie systemu, którego strona nie może obejść. Alarmy bezpieczeństwa dodatkowo zostaną pokazane w aplikacji.') }}</flux:text>
        </flux:card>
        </div>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Co ma mnie powiadamiać') }}</flux:heading>
                <flux:text>{{ __('Dotyczy wszystkich Twoich urządzeń. Dzwonek w aplikacji działa zawsze.') }}</flux:text>
            </div>
            <div class="space-y-3">
                @foreach ($kinds as $kind => [$label, $hint])
                    <flux:switch wire:model.live="prefs.{{ $kind }}" :label="$label" :description="$hint" />
                @endforeach
            </div>
        </flux:card>
    </div>

    <flux:card class="space-y-3">
        <flux:heading size="lg">{{ __('Moje urządzenia') }}</flux:heading>
        @forelse ($this->devices as $device)
            <div wire:key="d-{{ $device->id }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 pt-3 first:border-t-0 first:pt-0 dark:border-zinc-700/60">
                <div class="flex min-w-0 items-center gap-3">
                    <flux:icon :name="$device->platform === 'desktop' ? 'computer-desktop' : 'device-phone-mobile'" class="size-6 shrink-0 text-zinc-400" />
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $device->device_label ?? __('Urządzenie') }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ __('dodane :date', ['date' => $device->created_at->translatedFormat('j F Y')]) }}
                            @if ($device->last_success_at)
                                · {{ __('ostatnio dostarczono :ago', ['ago' => $device->last_success_at->diffForHumans()]) }}
                            @endif
                            @if ($device->failed_at && (! $device->last_success_at || $device->failed_at->gt($device->last_success_at)))
                                · <span class="text-amber-600">{{ __('ostatnia próba nieudana') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeDevice({{ $device->id }})" wire:confirm="{{ __('Usunąć to urządzenie? Przestanie otrzymywać powiadomienia.') }}">{{ __('Usuń') }}</flux:button>
            </div>
        @empty
            <flux:text>{{ __('Brak urządzeń z włączonymi powiadomieniami.') }}</flux:text>
        @endforelse
    </flux:card>
</div>
