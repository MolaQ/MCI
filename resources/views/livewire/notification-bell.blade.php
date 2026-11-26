<div wire:poll.{{ $pollSeconds }}s.visible>
    <flux:dropdown position="bottom" align="end">
        <button type="button" class="relative flex size-9 items-center justify-center rounded-mci text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                aria-label="{{ trans_choice('Powiadomienia (:count nowe)|Powiadomienia (:count nowe)|Powiadomienia (:count nowych)', $this->unreadCount) }}">
            <flux:icon name="bell" variant="outline" class="size-5" />
            @if ($this->unreadCount > 0)
                <span class="absolute -end-0.5 -top-0.5 flex min-w-4.5 items-center justify-center rounded-full bg-danger px-1 text-[0.65rem] font-semibold leading-4.5 text-white tabular-nums">
                    {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                </span>
            @endif
        </button>

        <flux:menu class="w-80 max-w-[calc(100vw-2rem)] space-y-1 p-2">
            <div class="flex items-center justify-between px-2 py-1">
                <flux:heading>{{ __('Powiadomienia') }}</flux:heading>
                @if ($this->unreadCount > 0)
                    <flux:button size="xs" variant="ghost" wire:click="markAllRead">{{ __('Oznacz jako przeczytane') }}</flux:button>
                @endif
            </div>
            @forelse ($this->latest as $n)
                <button type="button" wire:key="n-{{ $n->id }}" wire:click="open('{{ $n->id }}')"
                        @class(['flex w-full gap-3 rounded-mci p-2 text-start transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/60', 'bg-primary/5' => ! $n->read_at])>
                    @php($kind = (string) data_get($n->data, 'kind'))
                    <flux:icon :name="match (true) { $kind === 'mention' || str_ends_with($kind, '_mention') => 'at-symbol', str_starts_with($kind, 'task_') => 'check-circle', str_starts_with($kind, 'workflow_') => 'clipboard-document-check', str_starts_with($kind, 'project_') => 'rectangle-stack', default => 'megaphone' }" variant="mini" class="mt-0.5 shrink-0 text-zinc-400" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ data_get($n->data, 'title') }}</span>
                        <span class="block truncate text-sm text-zinc-500">{{ data_get($n->data, 'body') }}</span>
                        <span class="block text-xs text-zinc-400">{{ data_get($n->data, 'school') }} · {{ $n->created_at->diffForHumans() }}</span>
                    </span>
                    @if (! $n->read_at)
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"></span>
                    @endif
                </button>
            @empty
                <div class="px-2 py-6 text-center text-sm text-zinc-500">{{ __('Brak powiadomień') }}</div>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
