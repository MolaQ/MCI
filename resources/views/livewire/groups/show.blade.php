<div class="space-y-6">
    <x-ui.page-header :title="$group->name" :image="$group->logoUrl()" :subtitle="$group->type->label().' · '.$group->membership_mode->label()">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('groups.index')" wire:navigate>{{ __('Grupy') }}</flux:button>
            @can('join', $group)
                <flux:button variant="primary" icon="plus" wire:click="join">{{ __('Dołącz') }}</flux:button>
            @endcan
            @can('leave', $group)
                <flux:button icon="arrow-right-start-on-rectangle" wire:click="leave" wire:confirm="{{ __('Opuścić tę grupę?') }}">{{ __('Opuść grupę') }}</flux:button>
            @endcan
            @can('update', $group)
                <flux:button icon="pencil-square" :href="route('groups.edit', $group)" wire:navigate>{{ __('Edytuj') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($group->description)
        <flux:text class="max-w-3xl">{{ $group->description }}</flux:text>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('Członkowie') }} <span class="text-zinc-400">({{ $this->members->total() }})</span></flux:heading>
                <flux:input wire:model.live.debounce.300ms="search" size="sm" icon="magnifying-glass" :placeholder="__('Szukaj')" class="max-w-56" />
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($this->members as $member)
                    <div class="flex items-center justify-between gap-3 py-2" wire:key="m-{{ $member->id }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:avatar size="sm" :initials="$member->initials()" />
                            <div class="min-w-0">
                                <div class="truncate font-medium text-zinc-800 dark:text-white">{{ $member->name }}</div>
                                <div class="truncate text-xs text-zinc-500">{{ $member->email }}</div>
                            </div>
                            @if ($member->pivot->role === \App\Enums\GroupRole::Leader)
                                <flux:badge size="sm" color="amber">{{ __('Lider') }}</flux:badge>
                            @endif
                        </div>

                        @can('manageMembers', $group)
                            <flux:dropdown align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Akcje')" />
                                <flux:menu>
                                    @can('manageLeaders', $group)
                                        @if ($member->pivot->role === \App\Enums\GroupRole::Leader)
                                            <flux:menu.item icon="arrow-down" wire:click="setRole({{ $member->id }}, 'member')">{{ __('Zmień na członka') }}</flux:menu.item>
                                        @else
                                            <flux:menu.item icon="star" wire:click="setRole({{ $member->id }}, 'leader')">{{ __('Ustaw jako lidera') }}</flux:menu.item>
                                        @endif
                                    @endcan
                                    @if ($member->pivot->role !== \App\Enums\GroupRole::Leader || auth()->user()->can('manageLeaders', $group))
                                        <flux:menu.item icon="user-minus" variant="danger" wire:click="removeMember({{ $member->id }})"
                                                        wire:confirm="{{ __('Usunąć :name z grupy?', ['name' => $member->name]) }}">
                                            {{ __('Usuń z grupy') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </div>
                @empty
                    <x-ui.empty-state icon="users" :title="__('Brak członków')" />
                @endforelse
            </div>

            {{ $this->members->links() }}
        </flux:card>

        @can('manageMembers', $group)
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Dodaj osobę') }}</flux:heading>
                <flux:input wire:model.live.debounce.250ms="candidateSearch" icon="magnifying-glass" :placeholder="__('Wpisz min. 2 litery')" />

                <div class="space-y-1">
                    @foreach ($this->candidates as $candidate)
                        <button type="button" wire:click="addMember({{ $candidate->id }})" wire:key="c-{{ $candidate->id }}"
                                class="flex w-full items-center justify-between gap-2 rounded-mci px-2 py-1.5 text-start hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $candidate->name }}</span>
                                <span class="block truncate text-xs text-zinc-500">{{ $candidate->email }}</span>
                            </span>
                            <flux:icon.plus variant="micro" class="text-zinc-400" />
                        </button>
                    @endforeach
                    @if (mb_strlen(trim($candidateSearch)) >= 2 && $this->candidates->isEmpty())
                        <flux:text size="sm">{{ __('Nikogo nie znaleziono.') }}</flux:text>
                    @endif
                </div>

                @can('invite', $group)
                    <flux:separator />
                    <flux:button size="sm" icon="qr-code" class="w-full" :href="route('invites.create', ['grupa' => $group->id])" wire:navigate>
                        {{ __('Utwórz link / kod QR do tej grupy') }}
                    </flux:button>
                @endcan
            </flux:card>
        @endcan
    </div>
</div>
