<div class="space-y-6">
    <x-ui.page-header :title="__('Tryb demo')" :subtitle="__('Pokaz systemu na przykładowych danych, bez dotykania danych produkcyjnych.')" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <flux:card class="space-y-4" data-demo-status>
                <div class="flex flex-wrap items-center gap-3">
                    <span @class(['flex size-10 items-center justify-center rounded-mci', 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $on, 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => ! $on])>
                        <flux:icon :name="$on ? 'beaker' : 'shield-check'" variant="mini" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <flux:heading size="lg">{{ $on ? __('Tryb demo jest WŁĄCZONY') : __('Strona działa na danych produkcyjnych') }}</flux:heading>
                        <flux:text>
                            @if ($on)
                                {{ __('Wszyscy widzą dane demonstracyjne. Włączono :date (:by).', ['date' => isset($state['enabled_at']) ? \Illuminate\Support\Carbon::parse($state['enabled_at'])->translatedFormat('j M Y, H:i') : '—', 'by' => $state['by'] ?? '—']) }}
                            @else
                                {{ __('Dane demo są w osobnej bazie i nie mieszają się z prawdziwymi.') }}
                            @endif
                        </flux:text>
                    </div>
                </div>

                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-mci border border-zinc-200 p-3 dark:border-zinc-700"><dt class="text-xs text-zinc-500">{{ __('Baza główna') }}</dt><dd class="mt-0.5 font-mono">{{ $mainDatabase }}</dd></div>
                    <div class="rounded-mci border border-zinc-200 p-3 dark:border-zinc-700"><dt class="text-xs text-zinc-500">{{ __('Baza demo') }}</dt><dd class="mt-0.5 font-mono">{{ $demoDatabase }}</dd></div>
                    <div class="rounded-mci border border-zinc-200 p-3 dark:border-zinc-700"><dt class="text-xs text-zinc-500">{{ __('Dane demo') }}</dt><dd class="mt-0.5">{{ $ready ? __('przygotowane') : __('jeszcze nie utworzone') }}</dd></div>
                </dl>

                @if ($unsafe)
                    <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Tryb demo jest zablokowany')" :text="$unsafe" />
                @endif
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Potwierdzenie') }}</flux:heading>
                <flux:text>{{ __('Każda zmiana wymaga Twojego hasła i wpisania słowa DEMO. Po przełączeniu wszyscy zostaną wylogowani: baza demo ma własne konta.') }}</flux:text>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input type="password" wire:model="password" :label="__('Twoje hasło')" autocomplete="current-password" />
                    <flux:input wire:model="confirmation" :label="__('Wpisz DEMO')" autocomplete="off" />
                </div>
                <flux:error name="demo" />

                <div class="flex flex-wrap gap-2">
                    @if ($on)
                        <flux:button variant="primary" icon="shield-check" wire:click="disable" wire:confirm="{{ __('Wrócić do danych produkcyjnych? Wszyscy zostaną wylogowani.') }}">{{ __('Wyłącz tryb demo') }}</flux:button>
                    @else
                        <flux:button variant="danger" icon="beaker" wire:click="enable" :disabled="(bool) $unsafe" wire:confirm="{{ __('Przełączyć CAŁĄ stronę na dane demonstracyjne? Wszyscy zostaną wylogowani.') }}">{{ __('Włącz tryb demo') }}</flux:button>
                        <flux:button icon="arrow-path" wire:click="rebuild" :disabled="(bool) $unsafe" wire:confirm="{{ __('Odtworzyć bazę demo od zera? Baza główna nie zostanie zmieniona.') }}">{{ $ready ? __('Odtwórz dane demo') : __('Utwórz dane demo') }}</flux:button>
                    @endif
                </div>
            </flux:card>
        </div>

        <flux:card class="space-y-3 text-sm">
            <flux:heading size="lg">{{ __('Jak to działa') }}</flux:heading>
            <ul class="list-disc space-y-2 ps-5 text-zinc-600 dark:text-zinc-300">
                <li>{{ __('Dane demo są w osobnej bazie (DB_DEMO_DATABASE lub „<baza>_demo”). Jej nazwa musi zawierać „demo” i różnić się od bazy głównej.') }}</li>
                <li>{{ __('Przełącznik to plik w katalogu storage — poza obiema bazami. Gdy jest włączony, cała strona, kolejka i harmonogram używają bazy demo, osobnego katalogu obrazów i osobnej sesji.') }}</li>
                <li>{{ __('Odtwarzanie bazy demo czyści i wypełnia wyłącznie bazę demo. Baza główna nie jest nigdy zmieniana.') }}</li>
                <li>{{ __('Konto demo: :email / :password', ['email' => config('mci.demo.admin_email'), 'password' => config('mci.demo.admin_password')]) }}</li>
                <li>{{ __('Włączenie, wyłączenie i odtworzenie trafiają do Dziennika witryny bazy głównej.') }}</li>
                <li>{{ __('Z wiersza poleceń: php artisan mci:demo status | reset | on | off') }}</li>
            </ul>
        </flux:card>
    </div>
</div>
