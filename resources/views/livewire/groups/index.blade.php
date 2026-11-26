<div class="space-y-6">
    <x-ui.page-header :title="__('Grupy')" :subtitle="__('Zespoły, do których trafiają ogłoszenia, alarmy i zadania.')">
        <x-slot:actions>
            @can('create', \App\Models\Group::class)
                <flux:button variant="primary" icon="plus" :href="route('groups.create')" wire:navigate>{{ __('Nowa grupa') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Szukaj grupy')" class="sm:col-span-2" />
        <flux:select wire:model.live="type">
            <flux:select.option value="">{{ __('Wszystkie typy') }}</flux:select.option>
            @foreach ($types as $t)
                <flux:select.option :value="$t->key">{{ $t->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse ($this->groups as $group)
            <a href="{{ route('groups.show', $group) }}" wire:navigate wire:key="group-{{ $group->id }}"
               class="group block rounded-mci border border-zinc-200 bg-white p-4 transition hover:border-primary dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <x-app.group-chip :group="$group" />
                    @if (in_array($group->id, $this->myGroupIds, true))
                        <flux:badge size="sm" color="green">{{ __('Należysz') }}</flux:badge>
                    @elseif ($group->membership_mode === \App\Enums\MembershipMode::Open)
                        <flux:badge size="sm">{{ __('Otwarta') }}</flux:badge>
                    @endif
                </div>
                <div class="mt-1 ms-4.5 text-xs text-zinc-500">{{ $group->type->label() }}</div>
                @if ($group->description)
                    <flux:text size="sm" class="mt-3 line-clamp-2">{{ $group->description }}</flux:text>
                @endif
                <div class="mt-4 flex items-center justify-between text-xs text-zinc-500">
                    <span>{{ trans_choice(':count osoba|:count osoby|:count osób', $group->members_count) }}</span>
                    @if ($group->leaders->isNotEmpty())
                        <span class="truncate">{{ __('Lider') }}: {{ $group->leaders->pluck('name')->join(', ') }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="sm:col-span-2 xl:col-span-3">
                <x-ui.empty-state icon="user-group" :title="__('Brak grup')" />
            </div>
        @endforelse
    </div>
</div>
