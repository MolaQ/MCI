<div class="space-y-6" wire:poll.{{ $pollSeconds }}s.visible>
    <x-ui.page-header :title="__('Komunikaty')" :subtitle="__('Wiadomości skierowane do Ciebie — tylko to, co Cię dotyczy.')">
        <x-slot:actions>
            @if ($this->counts['unread'] > 0 && $filter !== 'sent')
                <flux:button size="sm" variant="ghost" icon="check" wire:click="markAllRead">{{ __('Oznacz wszystkie jako przeczytane') }}</flux:button>
            @endif
            @if ($canCompose)
                <flux:button :href="route('communication.create')" wire:navigate variant="primary" icon="pencil-square">{{ __('Nowy komunikat') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2" role="toolbar" aria-label="{{ __('Filtry') }}">
            <x-ui.pill wire:click="setFilter('all')" :active="$filter === 'all'">{{ __('Wszystkie') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('unread')" :active="$filter === 'unread'" :count="$this->counts['unread']">{{ __('Nieprzeczytane') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('important')" :active="$filter === 'important'" :count="$this->counts['important']"><flux:icon name="exclamation-circle" variant="micro" /> {{ __('Ważne') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('confirm')" :active="$filter === 'confirm'" :count="$this->counts['confirm']">{{ __('Do potwierdzenia') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('mentions')" :active="$filter === 'mentions'" :count="$this->counts['mentions']"><flux:icon name="at-symbol" variant="micro" /> {{ __('Wzmianki') }}</x-ui.pill>
            @if ($canCompose)
                <x-ui.pill wire:click="setFilter('sent')" :active="$filter === 'sent'"><flux:icon name="paper-airplane" variant="micro" /> {{ __('Wysłane') }}</x-ui.pill>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()->schools()->count() > 1)
                <x-ui.pill wire:click="$set('allSchools', false)" :active="! $allSchools">{{ __('Ta szkoła') }}</x-ui.pill>
                <x-ui.pill wire:click="$set('allSchools', true)" :active="$allSchools">{{ __('Wszystkie moje szkoły') }}</x-ui.pill>
            @endif
            <flux:input wire:model.live.debounce.300ms="search" size="sm" icon="magnifying-glass" :placeholder="__('Szukaj w tytułach')" class="w-full sm:w-56" />
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($this->items as $item)
            @php($unread = $filter !== 'sent' && ! $item->read_at)
            <a href="{{ route('communication.show', $item) }}" wire:navigate wire:key="a-{{ $item->id }}"
               @class([
                   'mci-fade-up group relative flex gap-4 rounded-mci border p-4 transition-colors duration-150',
                   'border-primary/30 bg-primary/5 hover:bg-primary/10' => $unread,
                   'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:bg-zinc-800' => ! $unread,
               ])>
                <div @class([
                    'flex size-10 shrink-0 items-center justify-center rounded-full',
                    'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300' => $item->priority === \App\Enums\AnnouncementPriority::Urgent,
                    'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' => $item->priority === \App\Enums\AnnouncementPriority::Important,
                    'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300' => $item->priority === \App\Enums\AnnouncementPriority::Normal,
                ])>
                    <flux:icon :name="$item->priority->icon()" variant="mini" />
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($unread)
                            <span class="size-2 rounded-full bg-primary" aria-label="{{ __('Nieprzeczytany') }}"></span>
                        @endif
                        <span @class(['truncate text-zinc-900 dark:text-white', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $item->title }}</span>
                        @if ($item->isPinned())
                            <flux:icon name="bookmark" variant="micro" class="text-zinc-400" aria-label="{{ __('Przypięty') }}" />
                        @endif
                        @if ($item->priority !== \App\Enums\AnnouncementPriority::Normal)
                            <flux:badge size="sm" :color="$item->priority->color()">{{ $item->priority->label() }}</flux:badge>
                        @endif
                        @if ($filter !== 'sent' && $item->reason === 'mention')
                            <flux:badge size="sm" color="blue" icon="at-symbol">{{ __('Wzmianka') }}</flux:badge>
                        @endif
                        @if ($item->requires_confirmation && $filter !== 'sent' && ! $item->confirmed_at)
                            <flux:badge size="sm" color="violet">{{ __('Wymaga potwierdzenia') }}</flux:badge>
                        @endif
                        @if ($item->is_public)
                            <flux:badge size="sm" icon="globe-alt">{{ __('Publiczny') }}</flux:badge>
                        @endif
                    </div>
                    <p class="line-clamp-2 text-sm text-zinc-600 dark:text-zinc-300">{{ \App\Support\RichText::excerpt($item->body, 220) }}</p>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                        @if ($filter === 'sent')
                            <span>{{ $item->published_at->isFuture() ? __('Zaplanowano: :date', ['date' => $item->published_at->translatedFormat('j M, H:i')]) : $item->published_at->diffForHumans() }}</span>
                            <span>{{ __('Przeczytało :read z :total', ['read' => $item->read_count, 'total' => $item->recipients_count]) }}</span>
                        @else
                            <span>{{ $item->author?->name ?? __('System') }}</span>
                            <span title="{{ $item->published_at->translatedFormat('j F Y, H:i') }}">{{ $item->published_at->diffForHumans() }}</span>
                        @endif
                        @if ($allSchools)
                            <span class="inline-flex items-center gap-1"><x-app.school-badge :school="$item->school" size="sm" /> {{ $item->school->short_name }}</span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <flux:card>
                <x-ui.empty-state icon="inbox" :title="$filter === 'all' ? __('Brak komunikatów') : __('Nic tutaj nie ma')"
                                  :description="__('Gdy ktoś wyśle komunikat do Twojej grupy lub wspomni o Tobie, zobaczysz go tutaj.')" />
            </flux:card>
        @endforelse
    </div>

    {{ $this->items->links() }}
</div>
