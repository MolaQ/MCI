<div class="space-y-6">
    <x-ui.page-header :title="$invite->label ?: ($invite->email ?: __('Zaproszenie'))"
                      :subtitle="$invite->kind->label().' · '.__('utworzone :date przez :name', ['date' => $invite->created_at->format('d.m.Y'), 'name' => $invite->creator?->name ?? '—'])">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('invites.index')" wire:navigate>{{ __('Zaproszenia') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 text-center">
            <div class="mx-auto w-fit rounded-mci bg-white p-2 [&_svg]:size-56">{!! $this->qr !!}</div>
            <flux:badge :color="$invite->status()->color()">{{ $invite->status()->label() }}</flux:badge>

            <div x-data="{ copied: false }" class="flex items-center gap-2">
                <flux:input readonly :value="$invite->url()" class="flex-1" size="sm" x-ref="link" />
                <flux:button size="sm" icon="clipboard"
                    x-on:click="navigator.clipboard.writeText($refs.link.querySelector('input')?.value ?? $refs.link.value); copied = true; setTimeout(() => copied = false, 1500)">
                    <span x-text="copied ? @js(__('Skopiowano')) : @js(__('Kopiuj'))"></span>
                </flux:button>
            </div>

            <div class="flex flex-wrap justify-center gap-2">
                <flux:button size="sm" icon="printer" :href="route('invites.poster', $invite)" target="_blank">{{ __('Plakat A4') }}</flux:button>
                <flux:button size="sm" icon="arrow-down-tray" :href="route('invites.qr', $invite)">{{ __('QR (SVG)') }}</flux:button>
            </div>
        </flux:card>

        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Szczegóły') }}</flux:heading>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-zinc-500">{{ __('Grupy') }}</dt>
                        <dd class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                            @forelse ($invite->groups as $g)
                                <span class="inline-flex items-center gap-1"><x-app.group-chip :group="$g" />@if ($g->pivot->group_role === 'leader')<flux:badge size="sm" color="amber">{{ __('Lider') }}</flux:badge>@endif</span>
                            @empty — @endforelse
                        </dd></div>
                    <div><dt class="text-zinc-500">{{ __('Rola w szkole') }}</dt><dd class="mt-1">{{ $invite->role_name ? \App\Enums\Role::from($invite->role_name)->label() : __('bez zmiany') }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Użycia') }}</dt><dd class="mt-1 tabular-nums">{{ $invite->uses_count }}{{ $invite->max_uses ? ' / '.$invite->max_uses : ' / '.__('bez limitu') }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Ważne do') }}</dt><dd class="mt-1">{{ $invite->expires_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
                    @if ($invite->email)
                        <div><dt class="text-zinc-500">{{ __('Dla') }}</dt><dd class="mt-1">{{ $invite->email }}</dd></div>
                    @endif
                    <div><dt class="text-zinc-500">{{ __('Nowe konta') }}</dt><dd class="mt-1">{{ $invite->allow_self_registration ? __('dozwolone (domena szkoły)') : __('tylko istniejące konta') }}</dd></div>
                </dl>
                @can('revoke', $invite)
                    <flux:button variant="danger" size="sm" icon="no-symbol" wire:click="revoke"
                                 wire:confirm="{{ __('Unieważnić zaproszenie? Link i kod QR przestaną działać, osoby, które już dołączyły, zostaną w grupach.') }}">
                        {{ __('Unieważnij') }}
                    </flux:button>
                @endcan
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('Kto dołączył') }} <span class="text-zinc-400">({{ $invite->uses_count }})</span></flux:heading>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse ($redemptions as $r)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="flex min-w-0 items-center gap-3">
                                <flux:avatar size="xs" :initials="$r->user?->initials() ?? '?'" />
                                <span class="truncate">{{ $r->user?->name ?? __('usunięte konto') }}</span>
                                @if ($r->created_account)<flux:badge size="sm" color="blue">{{ __('nowe konto') }}</flux:badge>@endif
                            </div>
                            <span class="shrink-0 text-xs text-zinc-500">{{ $r->redeemed_at->format('d.m.Y H:i') }}</span>
                        </div>
                    @empty
                        <flux:text>{{ __('Nikt jeszcze nie użył tego zaproszenia.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>
        </div>
    </div>
</div>
