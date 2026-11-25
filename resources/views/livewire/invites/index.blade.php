<div class="space-y-6">
    <x-ui.page-header :title="__('Zaproszenia')" :subtitle="__('Linki i kody QR, którymi osoby dołączają do szkoły i grup.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('invites.create')" wire:navigate>{{ __('Nowe zaproszenie') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-wrap gap-2">
        @foreach ($statuses as $s)
            <x-ui.pill :active="$status === $s->value" wire:click="setStatus('{{ $s->value }}')">{{ $s->label() }}</x-ui.pill>
        @endforeach
        <x-ui.pill :active="$status === 'all'" wire:click="setStatus('all')">{{ __('Wszystkie') }}</x-ui.pill>
        <span class="mx-1 w-px self-stretch bg-zinc-200 dark:bg-zinc-700"></span>
        @foreach ($kinds as $k)
            <x-ui.pill :active="$kind === $k->value" wire:click="setKind('{{ $k->value }}')">{{ $k->label() }}</x-ui.pill>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($this->invites as $invite)
            <a href="{{ route('invites.show', $invite) }}" wire:navigate wire:key="i-{{ $invite->id }}"
               class="flex flex-col gap-3 rounded-mci border border-zinc-200 bg-white p-4 transition hover:border-primary sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex min-w-0 items-start gap-3">
                    <flux:icon :name="$invite->kind === \App\Enums\InviteKind::Personal ? 'envelope' : 'qr-code'" class="mt-0.5 size-5 shrink-0 text-zinc-400" />
                    <div class="min-w-0">
                        <div class="truncate font-medium text-zinc-800 dark:text-white">
                            {{ $invite->label ?: ($invite->email ?: $invite->groups->pluck('name')->join(', ')) }}
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                            @foreach ($invite->groups as $g)
                                <x-app.group-chip :group="$g" />
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-3 text-xs text-zinc-500">
                    <span class="tabular-nums">{{ $invite->uses_count }}{{ $invite->max_uses ? ' / '.$invite->max_uses : '' }} {{ __('użyć') }}</span>
                    @if ($invite->expires_at)
                        <span>{{ __('do :date', ['date' => $invite->expires_at->format('d.m.Y')]) }}</span>
                    @endif
                    <flux:badge size="sm" :color="$invite->status()->color()">{{ $invite->status()->label() }}</flux:badge>
                </div>
            </a>
        @empty
            <x-ui.empty-state icon="qr-code" :title="__('Brak zaproszeń')" :description="__('Utwórz link lub kod QR, aby nauczyciele mogli sami dołączyć do grup.')" />
        @endforelse
    </div>

    {{ $this->invites->links() }}
</div>
