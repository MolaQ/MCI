{{-- One term row in Kategorie i typy. Vars: $term, $usage, $editable, $first, $last, $isHidden, $scope. --}}
<div wire:key="t-{{ $term->id }}" data-term="{{ $term->key }}"
    @class(['flex flex-col gap-3 border-t border-zinc-100 py-3 first-of-type:border-t-0 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700/60',
        'opacity-60' => ! $term->is_active || $isHidden])>
    <div class="flex min-w-0 items-center gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-mci border border-zinc-200 text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">
            <flux:icon :name="$term->icon()" variant="mini" />
        </span>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 font-medium text-zinc-900 dark:text-white">
                @if ($term->color)
                    <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $term->color() }}"></span>
                @endif
                <span class="truncate">{{ $term->name }}</span>
                @unless ($term->is_active)
                    <flux:badge size="sm" color="zinc" icon="archive-box">{{ __('archiwum') }}</flux:badge>
                @endunless
                @if ($isHidden)
                    <flux:badge size="sm" color="zinc" icon="eye-slash">{{ __('ukryta w szkole') }}</flux:badge>
                @endif
            </div>
            <div class="text-xs text-zinc-500">{{ trans_choice('{0} nieużywana|{1} używana w :count wpisie|[2,*] używana w :count wpisach', $usage, ['count' => $usage]) }}</div>
        </div>
    </div>

    <div class="flex shrink-0 flex-wrap items-center gap-1">
        @if ($editable)
            <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="move({{ $term->id }}, -1)" :disabled="$first" :aria-label="__('Wyżej')" />
            <flux:button size="sm" variant="ghost" icon="arrow-down" wire:click="move({{ $term->id }}, 1)" :disabled="$last" :aria-label="__('Niżej')" />
            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $term->id }})">{{ __('Edytuj') }}</flux:button>
            <flux:button size="sm" variant="ghost" :icon="$term->is_active ? 'archive-box' : 'arrow-uturn-left'" wire:click="toggleActive({{ $term->id }})">
                {{ $term->is_active ? __('Archiwizuj') : __('Przywróć') }}
            </flux:button>
            @if ($usage === 0)
                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $term->id }})"
                    wire:confirm="{{ __('Usunąć „:n”? Tej operacji nie można cofnąć.', ['n' => $term->name]) }}">{{ __('Usuń') }}</flux:button>
            @endif
        @elseif ($scope === 'school' && $mayCustomise && $term->isGlobal())
            <flux:button size="sm" variant="ghost" :icon="$isHidden ? 'eye' : 'eye-slash'" wire:click="toggleHidden({{ $term->id }})">
                {{ $isHidden ? __('Pokaż w szkole') : __('Ukryj w szkole') }}
            </flux:button>
        @endif
    </div>
</div>
