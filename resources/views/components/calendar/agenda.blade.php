@props(['items', 'day' => null, 'empty' => null, 'limit' => 12, 'navigate' => true])
{{-- Today's agenda: all-day items first, then by time; past items dimmed, running ones marked. --}}
@php($day ??= now())
@php($list = $items->take($limit))
<ol {{ $attributes->class('relative space-y-0.5') }} data-agenda>
    @forelse ($list as $item)
        @php($tag = $item->url ? 'a' : 'div')
        <li wire:key="ag-{{ $item->key() }}">
            <{{ $tag }} @if ($item->url) href="{{ $item->url }}" @if ($navigate) wire:navigate @endif @endif
                @class(['group grid grid-cols-[6.25rem_0.75rem_minmax(0,1fr)] items-start gap-x-3 rounded-mci px-2 py-2.5 transition',
                        'hover:bg-zinc-50 dark:hover:bg-zinc-800/60' => $item->url,
                        'opacity-55' => $item->isOver()])>
                <span class="whitespace-nowrap pt-px text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $item->timeLabel($day) }}</span>
                <span class="relative mt-1.5 flex size-2.5">
                    @if ($item->isNow())
                        <span class="absolute inline-flex size-full animate-ping rounded-full {{ $item->tone() }} opacity-60 motion-reduce:animate-none"></span>
                    @endif
                    <span class="relative inline-flex size-2.5 rounded-full {{ $item->tone() }}"></span>
                </span>
                <span class="min-w-0">
                    <span class="flex items-center gap-2">
                        <span @class(['truncate font-medium text-zinc-900 dark:text-white', 'line-through decoration-zinc-400' => $item->done, 'group-hover:text-primary' => $item->url])>{{ $item->title }}</span>
                        @if ($item->isNow())
                            <span class="shrink-0 rounded-full bg-emerald-500/15 px-2 py-px text-[0.6875rem] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('Teraz') }}</span>
                        @elseif ($item->alert)
                            <span class="shrink-0 rounded-full bg-red-500/10 px-2 py-px text-[0.6875rem] font-semibold uppercase tracking-wide text-red-600 dark:text-red-300">{{ __('Po terminie') }}</span>
                        @endif
                    </span>
                    <span class="mt-0.5 flex items-center gap-1 truncate text-xs text-zinc-500 dark:text-zinc-400">
                        <flux:icon :name="$item->icon" variant="micro" class="shrink-0 opacity-70" />
                        <span class="truncate">{{ $item->meta ?? __('Wydarzenie') }}</span>
                    </span>
                </span>
            </{{ $tag }}>
        </li>
    @empty
        <li class="flex items-center gap-3 rounded-mci border border-dashed border-zinc-200 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700">
            <flux:icon name="sun" class="size-6 shrink-0 text-zinc-300 dark:text-zinc-600" />
            {{ $empty ?? __('Na dziś nic nie zaplanowano.') }}
        </li>
    @endforelse
    @if ($items->count() > $limit)
        <li class="px-2 pt-1 text-xs text-zinc-500">{{ trans_choice('i jeszcze :count pozycja|i jeszcze :count pozycje|i jeszcze :count pozycji', $items->count() - $limit) }}</li>
    @endif
</ol>
