<div class="space-y-6">
    <x-ui.page-header :title="__('Szablony obiegów')" :subtitle="$scope === 'site'
        ? __('Szablony wspólne dla wszystkich szkół (jeśli szkoły mają włączone wspólne szablony).')
        : __('Wnioski, które mogą składać osoby z tej szkoły: etapy, kto decyduje, terminy i wymagane pola.')">
        <x-slot:actions>
            <flux:button :href="$editRoute()" wire:navigate variant="primary" icon="plus">{{ __('Nowy szablon') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <flux:card class="divide-y divide-zinc-100 !p-0 dark:divide-zinc-700/70">
        @forelse ($this->templates as $i => $t)
            <div wire:key="t-{{ $t->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3">
                <span @class(['flex size-10 shrink-0 items-center justify-center rounded-mci', 'bg-primary/10 text-primary' => $t->is_active, 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' => ! $t->is_active])><flux:icon :name="$t->icon" variant="mini" /></span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ $editRoute($t) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-white">{{ $t->name }}</a>
                        @unless ($t->is_active)<flux:badge size="sm">{{ __('wyłączony') }}</flux:badge>@endunless
                    </div>
                    <div class="text-xs text-zinc-500">
                        {{ trans_choice(':count etap|:count etapy|:count etapów', $t->stages_count) }} ·
                        {{ trans_choice(':count pole|:count pola|:count pól', count($t->fields ?? [])) }} ·
                        {{ trans_choice(':count wniosek|:count wnioski|:count wniosków', $t->requests_count) }}@if ($t->open_count) ({{ __(':n w toku', ['n' => $t->open_count]) }})@endif
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="move({{ $t->id }}, -1)" :disabled="$i === 0" :aria-label="__('Wyżej')" />
                    <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="move({{ $t->id }}, 1)" :disabled="$i === $this->templates->count() - 1" :aria-label="__('Niżej')" />
                    <flux:button size="xs" variant="ghost" :icon="$t->is_active ? 'eye-slash' : 'eye'" wire:click="toggle({{ $t->id }})">{{ $t->is_active ? __('Wyłącz') : __('Włącz') }}</flux:button>
                    <flux:button size="xs" variant="ghost" icon="pencil-square" :href="$editRoute($t)" wire:navigate>{{ __('Edytuj') }}</flux:button>
                    <flux:button size="xs" variant="ghost" icon="trash" class="!text-red-600" wire:click="delete({{ $t->id }})" wire:confirm="{{ __('Usunąć szablon? Złożone wnioski zostaną zachowane.') }}" :aria-label="__('Usuń')" />
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="clipboard-document-check" :title="__('Brak szablonów')" :description="__('Dodaj pierwszy szablon, np. „Zgoda na wycieczkę” albo „Zakup sprzętu”.')" />
        @endforelse
    </flux:card>

    @if ($this->systemTemplates->isNotEmpty())
        <div class="space-y-2">
            <flux:heading>{{ __('Szablony wspólne portalu') }}</flux:heading>
            <flux:text>{{ __('Przygotowuje je administrator portalu; są dostępne w tej szkole, gdy włączone są wspólne szablony obiegów.') }}</flux:text>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->systemTemplates as $t)
                    <flux:badge :icon="$t->icon">{{ $t->name }} · {{ trans_choice(':count etap|:count etapy|:count etapów', $t->stages_count) }}</flux:badge>
                @endforeach
            </div>
        </div>
    @endif
</div>
