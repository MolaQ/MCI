@php($school = app(\App\Support\CurrentSchool::class)->get())
@php($features = app(\App\Support\Features::class))
<div class="space-y-8">
    <x-ui.page-header :title="__('Dzień dobry, :name', ['name' => auth()->user()->first_name])"
                      :subtitle="now()->translatedFormat('l, j F Y').($school ? ' · '.$school->name : '')">
        @if ($features->enabled(\App\Enums\Feature::PublicFront, null))
            <x-slot:actions>
                <flux:button :href="route('home', ['podglad' => 1])" size="sm" variant="ghost" icon="globe-alt">{{ __('Strona publiczna') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($school)
        {{-- What needs attention today, in this school --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-dashboard-summary>
            @feature('announcements')
                <x-ui.stat :label="__('Nieprzeczytane komunikaty')" :value="$this->summary['unread']" icon="megaphone" :href="route('communication.index')" />
            @endfeature
            @feature('tasks')
                <x-ui.stat :label="$this->summary['overdue'] ? __('Zadania na dziś (zaległe: :n)', ['n' => $this->summary['overdue']]) : __('Zadania na dziś')" :value="$this->summary['tasks']" icon="check-circle" :href="route('tasks.index')" />
            @endfeature
            @feature('workflows')
                <x-ui.stat :label="__('Do mojej decyzji')" :value="$this->summary['decisions']" icon="clipboard-document-check" :href="route('workflows.index')" />
            @endfeature
            @feature('events')
                <x-ui.stat :label="__('Wydarzenia w 7 dni')" :value="$this->summary['events']" icon="calendar-days" :href="route('events.index')" />
            @endfeature
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-3">
            @feature('announcements')
            <flux:card class="min-w-0 space-y-3 xl:col-span-2" data-dashboard-announcements>
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Komunikaty szkoły') }}</flux:heading>
                    <flux:button :href="route('communication.index')" wire:navigate size="xs" variant="ghost" icon-trailing="arrow-right">{{ __('Centrum komunikatów') }}</flux:button>
                </div>
                @forelse ($this->latestAnnouncements as $a)
                    <a href="{{ route('communication.show', $a) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                        <flux:icon :name="$a->priority->icon()" variant="mini" @class(['shrink-0', 'text-red-600' => $a->priority === \App\Enums\AnnouncementPriority::Urgent, 'text-amber-600' => $a->priority === \App\Enums\AnnouncementPriority::Important, 'text-zinc-400' => $a->priority === \App\Enums\AnnouncementPriority::Normal]) />
                        <span class="min-w-0 flex-1">
                            <span @class(['block truncate text-sm', 'font-semibold' => ! $a->pivot->read_at])>{{ $a->title }}</span>
                            <span class="block truncate text-xs text-zinc-500">{{ $a->author?->name }}</span>
                        </span>
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

            <div class="min-w-0 space-y-6">
                @feature('workflows')
                <flux:card class="space-y-3" data-dashboard-decisions>
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ __('Do mojej decyzji') }}</flux:heading>
                        <flux:button :href="route('workflows.index')" wire:navigate size="xs" variant="ghost" icon-trailing="arrow-right">{{ __('Obiegi') }}</flux:button>
                    </div>
                    @forelse ($this->decisions as $r)
                        <a href="{{ route('workflows.show', $r) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <flux:icon name="clipboard-document-check" variant="mini" class="shrink-0 text-zinc-400" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $r->title }}</span>
                                <span class="block truncate text-xs text-zinc-500">{{ $r->requester?->name }}@if ($r->school_id !== $school->id) · {{ $r->school?->short_name }}@endif</span>
                            </span>
                            @if ($r->stage_due_at)
                                <span @class(['shrink-0 text-xs', 'font-semibold text-red-600' => $r->isOverdue(), 'text-zinc-500' => ! $r->isOverdue()])>{{ $r->stage_due_at->diffForHumans() }}</span>
                            @endif
                        </a>
                    @empty
                        <flux:text>{{ __('Nic nie czeka na Twoją decyzję.') }}</flux:text>
                    @endforelse
                </flux:card>
                @endfeature

                @feature('tasks')
                <flux:card class="space-y-3" data-dashboard-tasks>
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ __('Moje zadania') }}</flux:heading>
                        <flux:button :href="route('tasks.index')" wire:navigate size="xs" variant="ghost" icon-trailing="arrow-right">{{ __('Wszystkie zadania') }}</flux:button>
                    </div>
                    @forelse ($this->myTasks as $t)
                        <a href="{{ route('tasks.show', $t) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <flux:icon :name="$t->status === \App\Enums\TaskStatus::InProgress ? 'play-circle' : 'stop-circle'" variant="mini" @class(['shrink-0', 'text-sky-500' => $t->status === \App\Enums\TaskStatus::InProgress, 'text-zinc-400' => $t->status !== \App\Enums\TaskStatus::InProgress]) />
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $t->title }}</span>
                            @if ($t->priority->value >= 3)
                                <flux:icon :name="$t->priority->icon()" variant="micro" @class(['shrink-0', 'text-amber-500' => $t->priority->value === 3, 'text-red-600' => $t->priority->value === 4]) />
                            @endif
                            @if ($t->due_at)
                                <span @class(['shrink-0 text-xs', 'font-semibold text-red-600' => $t->isOverdue(), 'text-zinc-500' => ! $t->isOverdue()])>{{ $t->dueLabel() }}</span>
                            @endif
                        </a>
                    @empty
                        <flux:text>{{ __('Brak zadań na ten tydzień. Dobra robota!') }}</flux:text>
                    @endforelse
                </flux:card>
                @endfeature
            </div>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-2 xl:grid-cols-3">
            @feature('events')
            <flux:card class="min-w-0 space-y-3" data-dashboard-events>
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
                            <div class="truncate text-xs text-zinc-500">{{ $event->all_day ? __('cały dzień') : $event->starts_at->format('H:i') }} · {{ $event->visibility->label() }}</div>
                        </div>
                    </a>
                @empty
                    <flux:text>{{ __('Brak zaplanowanych wydarzeń.') }}</flux:text>
                @endforelse
            </flux:card>
            @endfeature

            @feature('workflows')
            <flux:card class="min-w-0 space-y-3" data-dashboard-requests>
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Moje wnioski w toku') }}</flux:heading>
                    <flux:button :href="route('workflows.create')" wire:navigate size="xs" variant="ghost" icon="plus">{{ __('Nowy wniosek') }}</flux:button>
                </div>
                @forelse ($this->myRequests as $r)
                    <a href="{{ route('workflows.show', $r) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                        <flux:icon :name="$r->status->icon()" variant="mini" class="shrink-0 text-zinc-400" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ $r->title }}</span>
                            <span class="block truncate text-xs text-zinc-500">{{ $r->currentStage()['name'] ?? $r->template_name }}</span>
                        </span>
                        <flux:badge size="sm" :color="$r->status->color()">{{ $r->status->label() }}</flux:badge>
                    </a>
                @empty
                    <flux:text>{{ __('Nie masz otwartych wniosków.') }}</flux:text>
                @endforelse
            </flux:card>
            @endfeature

            <flux:card class="min-w-0 space-y-3">
                <flux:heading size="lg">{{ __('Moje grupy') }}</flux:heading>
                @forelse ($this->myGroups as $group)
                    <a href="{{ route('groups.show', $group) }}" wire:navigate
                       class="flex items-center justify-between gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
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
        </div>
    @else
        <x-ui.empty-state icon="building-library" :title="__('Nie należysz jeszcze do żadnej szkoły')"
                          :description="__('Poproś administratora o zaproszenie albo użyj kodu z plakatu.')" />
    @endif

    @if ($this->adminStats)
        <section class="space-y-3" aria-labelledby="admin-stats">
            <flux:heading id="admin-stats" size="sm" class="text-zinc-500">{{ __('Administracja szkoły') }}</flux:heading>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
                <x-ui.stat :label="__('Aktywne konta')" :value="$this->adminStats['active']" icon="check-circle" />
                <x-ui.stat :label="__('Oczekujące konta')" :value="$this->adminStats['invited']" icon="envelope" />
                <x-ui.stat :label="__('Zawieszone')" :value="$this->adminStats['suspended']" icon="no-symbol" />
                <x-ui.stat :label="__('Grupy')" :value="$this->adminStats['groups']" icon="user-group" />
                <x-ui.stat :label="__('Bez grupy')" :value="$this->adminStats['without_groups']" icon="exclamation-triangle"
                           :href="route('admin.users.index', ['withoutGroups' => 1])" />
            </div>
        </section>
    @endif
</div>
