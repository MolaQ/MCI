<div class="space-y-6">
    <x-ui.page-header :title="__('Import użytkowników z CSV')" :subtitle="__('Najpierw sprawdź plik — nic nie zostanie zapisane, dopóki nie klikniesz „Importuj”.')">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.users.index')" wire:navigate>{{ __('Wróć') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-5 lg:col-span-2">
            <flux:input type="file" wire:model="file" accept=".csv,text/csv" :label="__('Plik CSV')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="defaultRole" :label="__('Rola, gdy kolumna „rola” jest pusta')">
                    @foreach ($roles as $r)
                        <flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="space-y-3 pt-6">
                    <flux:checkbox wire:model.live="updateExisting" :label="__('Aktualizuj istniejące konta (po e-mailu)')" />
                    <flux:checkbox wire:model="sendSetupLinks" :label="__('Wyślij nowym osobom link do ustawienia hasła')" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="check" icon="magnifying-glass">{{ __('Sprawdź plik') }}</flux:button>
                @if ($preview && ($preview['created'] + $preview['updated']) > 0)
                    <flux:button variant="primary" wire:click="import" icon="arrow-up-tray"
                                 wire:confirm="{{ __('Zaimportować :count kont?', ['count' => $preview['created'] + $preview['updated']]) }}">
                        {{ __('Importuj') }}
                    </flux:button>
                @endif
            </div>

            @if ($preview)
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <x-ui.stat :label="__('Wierszy')" :value="$preview['rows']" />
                    <x-ui.stat :label="__('Nowe konta')" :value="$preview['created']" />
                    <x-ui.stat :label="__('Aktualizacje')" :value="$preview['updated']" />
                    <x-ui.stat :label="__('Pominięte (istnieją)')" :value="$preview['skipped']" />
                </div>

                @if ($preview['errors'])
                    <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Wiersze z błędami nie zostaną zaimportowane')">
                        <flux:callout.text>
                            <ul class="mt-2 list-disc space-y-1 ps-4">
                                @foreach ($preview['errors'] as $line => $error)
                                    <li>{{ __('Wiersz :line', ['line' => $line]) }}: {{ $error }}</li>
                                @endforeach
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                @endif
            @endif
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Format pliku') }}</flux:heading>
            <flux:text>{{ __('Pierwszy wiersz to nagłówki. Separator: średnik lub przecinek. Kodowanie: UTF-8 (Excel: „CSV UTF-8”).') }}</flux:text>
            <ul class="list-disc space-y-1 ps-4 text-sm text-zinc-600 dark:text-zinc-300">
                <li><code>imie</code>, <code>nazwisko</code>, <code>email</code> — {{ __('wymagane') }}</li>
                <li><code>rola</code> — admin, coordinator, teacher, staff, student</li>
                <li><code>grupy</code> — {{ __('nazwy grup oddzielone „|”; dopisz „:lider”, aby nadać rolę lidera') }}</li>
                <li><code>telefon</code> — {{ __('opcjonalnie') }}</li>
            </ul>
            <flux:button size="sm" icon="arrow-down-tray" wire:click="template">{{ __('Pobierz szablon') }}</flux:button>
        </flux:card>
    </div>
</div>
