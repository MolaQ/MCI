@php($a = $this->announcement)
<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <flux:button :href="route('communication.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">{{ __('Komunikaty') }}</flux:button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <article @class(['space-y-5', 'lg:col-span-2' => $this->stats, 'lg:col-span-3' => ! $this->stats])>
            <flux:card class="space-y-5">
                <header class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$a->priority->color()" :icon="$a->priority->icon()">{{ $a->priority->label() }}</flux:badge>
                        @if ($a->is_public)
                            <flux:badge size="sm" icon="globe-alt">{{ __('Publiczny') }}</flux:badge>
                        @endif
                        @if ($a->isPinned())
                            <flux:badge size="sm" icon="bookmark">{{ __('Przypięty') }}</flux:badge>
                        @endif
                        @if ($a->published_at->isFuture())
                            <flux:badge size="sm" color="sky" icon="clock">{{ __('Zaplanowany na :date', ['date' => $a->published_at->translatedFormat('j F, H:i')]) }}</flux:badge>
                        @endif
                    </div>
                    <flux:heading size="xl" level="1">{{ $a->title }}</flux:heading>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                        <span class="inline-flex items-center gap-2">
                            <flux:avatar size="xs" :initials="$a->author?->initials() ?? '?'" />
                            {{ $a->author?->name ?? __('System') }}
                        </span>
                        <span>{{ $a->published_at->translatedFormat('j F Y, H:i') }}</span>
                        <span class="inline-flex items-center gap-1"><x-app.school-badge :school="$a->school" size="sm" /> {{ $a->school->short_name }}</span>
                        @if ($a->expires_at)
                            <span>{{ __('Wygasa :date', ['date' => $a->expires_at->translatedFormat('j F')]) }}</span>
                        @endif
                    </div>
                </header>

                <div class="mci-prose text-zinc-800 dark:text-zinc-100">{!! $a->body !!}</div>

                @if ($a->requires_confirmation && $this->receipt)
                    <div class="flex flex-col gap-3 rounded-mci border border-violet-200 bg-violet-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-violet-500/30 dark:bg-violet-500/10">
                        @if ($this->receipt->confirmed_at)
                            <div class="flex items-center gap-2 text-sm text-violet-800 dark:text-violet-200">
                                <flux:icon name="check-badge" variant="mini" />
                                {{ __('Potwierdzono :date', ['date' => \Illuminate\Support\Carbon::parse($this->receipt->confirmed_at)->translatedFormat('j F, H:i')]) }}
                            </div>
                        @else
                            <flux:text class="!text-violet-800 dark:!text-violet-200">{{ __('Autor prosi o potwierdzenie, że zapoznałeś(-aś) się z treścią.') }}</flux:text>
                            <flux:button wire:click="confirm" variant="primary" icon="check">{{ __('Potwierdzam zapoznanie się') }}</flux:button>
                        @endif
                    </div>
                @endif
            </flux:card>
        </article>

        @if ($this->stats)
            @php($s = $this->stats)
            <aside class="space-y-4">
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('Dostarczenie') }}</flux:heading>
                    <div class="space-y-3">
                        @foreach ([['read', __('Przeczytało')], ...($a->requires_confirmation ? [['confirmed', __('Potwierdziło')]] : [])] as [$key, $label])
                            @php($pct = $s['total'] ? round($s[$key] / $s['total'] * 100) : 0)
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm"><span>{{ $label }}</span><span class="tabular-nums text-zinc-500">{{ $s[$key] }} / {{ $s['total'] }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                                    <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <x-ui.pill wire:click="$set('statsFilter', 'unread')" :active="$statsFilter === 'unread'">{{ __('Nie przeczytali') }}</x-ui.pill>
                        <x-ui.pill wire:click="$set('statsFilter', 'read')" :active="$statsFilter === 'read'">{{ __('Przeczytali') }}</x-ui.pill>
                        @if ($a->requires_confirmation)
                            <x-ui.pill wire:click="$set('statsFilter', 'unconfirmed')" :active="$statsFilter === 'unconfirmed'">{{ __('Bez potwierdzenia') }}</x-ui.pill>
                        @endif
                    </div>
                    <div class="max-h-80 space-y-1 overflow-y-auto" wire:poll.30s.visible>
                        @forelse ($this->people as $person)
                            <div class="flex items-center gap-2 text-sm" wire:key="p-{{ $person->id }}">
                                <flux:avatar size="xs" :initials="$person->initials()" />
                                <span class="flex-1 truncate">{{ $person->name }}</span>
                                @if ($person->pivot->reason === 'mention')
                                    <flux:icon name="at-symbol" variant="micro" class="text-zinc-400" />
                                @endif
                            </div>
                        @empty
                            <flux:text size="sm">{{ __('Nikogo na tej liście.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>

                @can('delete', $a)
                    <flux:modal.trigger name="withdraw">
                        <flux:button variant="ghost" icon="trash" class="w-full">{{ __('Wycofaj komunikat') }}</flux:button>
                    </flux:modal.trigger>
                    <flux:modal name="withdraw" class="max-w-sm">
                        <div class="space-y-4">
                            <flux:heading size="lg">{{ __('Wycofać komunikat?') }}</flux:heading>
                            <flux:text>{{ __('Zniknie z listy odbiorców i ze strony publicznej. Wpis zostaje w logach szkoły.') }}</flux:text>
                            <div class="flex justify-end gap-2">
                                <flux:modal.close><flux:button variant="ghost">{{ __('Anuluj') }}</flux:button></flux:modal.close>
                                <flux:button wire:click="withdraw" variant="danger">{{ __('Wycofaj') }}</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @endcan
            </aside>
        @endif
    </div>
</div>
