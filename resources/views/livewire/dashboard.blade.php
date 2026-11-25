<div class="space-y-8">
    <x-ui.page-header :title="__('Dzień dobry, :name', ['name' => auth()->user()->first_name])"
                      :subtitle="now()->translatedFormat('l, j F Y')" />

    @if ($this->adminStats)
        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <x-ui.stat :label="__('Aktywne konta')" :value="$this->adminStats['active']" icon="check-circle" />
            <x-ui.stat :label="__('Oczekujące konta')" :value="$this->adminStats['invited']" icon="envelope" />
            <x-ui.stat :label="__('Zawieszone')" :value="$this->adminStats['suspended']" icon="no-symbol" />
            <x-ui.stat :label="__('Grupy')" :value="$this->adminStats['groups']" icon="user-group" />
            <x-ui.stat :label="__('Bez grupy')" :value="$this->adminStats['without_groups']" icon="exclamation-triangle"
                       :href="route('admin.users.index', ['withoutGroups' => 1])" />
        </div>
    @endif

    @feature('announcements')
    <flux:card class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Komunikaty') }}</flux:heading>
            <flux:button :href="route('communication.index')" wire:navigate size="xs" variant="ghost" icon-trailing="arrow-right">{{ __('Centrum komunikatów') }}</flux:button>
        </div>
        @forelse ($this->latestAnnouncements as $a)
            <a href="{{ route('communication.show', $a) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                <flux:icon :name="$a->priority->icon()" variant="mini" @class(['shrink-0', 'text-red-600' => $a->priority === \App\Enums\AnnouncementPriority::Urgent, 'text-amber-600' => $a->priority === \App\Enums\AnnouncementPriority::Important, 'text-zinc-400' => $a->priority === \App\Enums\AnnouncementPriority::Normal]) />
                <span @class(['min-w-0 flex-1 truncate text-sm', 'font-semibold' => ! $a->pivot->read_at])>{{ $a->title }}</span>
                <span class="shrink-0 text-xs text-zinc-500">{{ $a->published_at->diffForHumans() }}</span>
                @if (! $a->pivot->read_at)
                    <span class="size-2 shrink-0 rounded-full bg-primary"></span>
                @endif
            </a>
        @empty
            <flux:text>{{ __('Nie masz jeszcze komunikatów.') }}</flux:text>
        @endforelse
    </flux:card>
    @endfeature

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="lg:col-span-2 space-y-4">
            <flux:heading size="lg">{{ __('Moje grupy') }}</flux:heading>

            @forelse ($this->myGroups as $group)
                <a href="{{ route('groups.show', $group) }}" wire:navigate
                   class="flex items-center justify-between gap-3 rounded-mci px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-app.group-chip :group="$group" />
                        @if ($group->pivot->role === \App\Enums\GroupRole::Leader)
                            <flux:badge size="sm" color="amber">{{ __('Lider') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text size="sm">{{ trans_choice(':count osoba|:count osoby|:count osób', $group->members_count) }}</flux:text>
                </a>
            @empty
                <x-ui.empty-state icon="user-group" :title="__('Nie należysz jeszcze do żadnej grupy')"
                                  :description="__('Administrator lub lider grupy doda Cię do odpowiednich zespołów.')" />
            @endforelse
        </flux:card>

        @feature('events')
        <flux:card class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Najbliższe wydarzenia') }}</flux:heading>
                <flux:button :href="route('events.index')" wire:navigate size="xs" variant="ghost" icon-trailing="arrow-right">{{ __('Wszystkie') }}</flux:button>
            </div>
            @forelse ($this->upcomingEvents as $event)
                <a href="{{ route('events.show', $event) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <div class="flex w-11 shrink-0 flex-col items-center rounded-mci bg-primary/10 py-1 text-primary">
                        <span class="text-[0.6rem] uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
                        <span class="text-base font-bold leading-none">{{ $event->starts_at->format('j') }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium">{{ $event->title }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $event->all_day ? __('cały dzień') : $event->starts_at->format('H:i') }} · {{ $event->school->short_name }}</div>
                    </div>
                </a>
            @empty
                <flux:text>{{ __('Brak zaplanowanych wydarzeń.') }}</flux:text>
            @endforelse
        </flux:card>
        @endfeature
    </div>
</div>
