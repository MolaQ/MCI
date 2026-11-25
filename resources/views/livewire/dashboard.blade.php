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

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Wkrótce w MCI') }}</flux:heading>
            <flux:text>{{ __('Kolejne moduły pojawią się tutaj: alarmy bezpieczeństwa z potwierdzeniem odbioru, ogłoszenia dla wybranych grup, zadania i wydarzenia.') }}</flux:text>
        </flux:card>
    </div>
</div>
