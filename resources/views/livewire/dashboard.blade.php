@php($school = app(\App\Support\CurrentSchool::class)->get())
@php($features = app(\App\Support\Features::class))
@php($on = fn (\App\Enums\Feature $f) => $school && $features->enabled($f))
@php($user = auth()->user())
@php($card = 'rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-zinc-700/80 dark:bg-zinc-900')
@php($cardHead = 'mb-4 flex flex-wrap items-center gap-x-3 gap-y-2')
@php($h2 = 'flex items-center gap-2 text-base font-semibold tracking-tight text-zinc-900 dark:text-white')
@php($more = 'ms-auto inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-primary dark:text-zinc-400')
<div class="space-y-8 pb-6">
    {{-- Greeting, date and quick actions --}}
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium text-zinc-500 first-letter:uppercase dark:text-zinc-400">{{ now()->translatedFormat('l, j F Y') }}@if ($school) · {{ $school->name }}@endif</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">{{ __('Dzień dobry, :name', ['name' => $user->first_name]) }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($school)
                @php($create = array_filter([
                    $on(\App\Enums\Feature::Announcements) && $user->can('create', \App\Models\Announcement::class) ? ['megaphone', __('Komunikat'), route('communication.create')] : null,
                    $on(\App\Enums\Feature::Events) && $user->can('create', \App\Models\Event::class) ? ['calendar-days', __('Wydarzenie'), route('events.create')] : null,
                    $on(\App\Enums\Feature::Tasks) ? ['check-circle', __('Zadanie'), route('tasks.create')] : null,
                    $on(\App\Enums\Feature::Workflows) ? ['clipboard-document-check', __('Wniosek'), route('workflows.create')] : null,
                    $on(\App\Enums\Feature::Projects) && $user->can('create', \App\Models\Project::class) ? ['rectangle-stack', __('Projekt'), route('projects.create')] : null,
                ]))
                @if ($create)
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="primary" icon="plus" icon-trailing="chevron-down" size="sm">{{ __('Utwórz') }}</flux:button>
                        <flux:menu>
                            @foreach ($create as [$icon, $label, $href])
                                <flux:menu.item :icon="$icon" :href="$href" wire:navigate>{{ $label }}</flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                @endif
            @endif
            @if ($features->enabled(\App\Enums\Feature::PublicFront, null))
                <flux:button :href="route('home', ['podglad' => 1])" size="sm" variant="ghost" icon="globe-alt">{{ __('Strona publiczna') }}</flux:button>
            @endif
        </div>
    </header>

    @if ($school)
        {{-- At a glance --}}
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4" data-dashboard-summary>
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

        {{-- 1. Today --}}
        <div class="grid items-start gap-6 xl:grid-cols-12">
            <section class="{{ $card }} min-w-0 xl:col-span-7" aria-labelledby="agenda-h" data-dashboard-agenda>
                <div class="{{ $cardHead }}">
                    <h2 id="agenda-h" class="{{ $h2 }}"><flux:icon name="sun" variant="mini" class="text-amber-500" /> {{ __('Agenda dnia') }}</h2>
                    <span class="text-sm text-zinc-500 first-letter:uppercase">{{ now()->translatedFormat('l, j F') }}</span>
                </div>

                @if ($this->keyAnnouncements->isNotEmpty())
                    <div class="mb-4 space-y-2">
                        @foreach ($this->keyAnnouncements as $a)
                            <a href="{{ route('communication.show', $a) }}" wire:navigate
                               @class(['flex items-center gap-3 rounded-xl border px-3.5 py-2.5 text-sm transition',
                                       'border-red-200 bg-red-50 text-red-900 hover:border-red-300 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100' => $a->priority === \App\Enums\AnnouncementPriority::Urgent,
                                       'border-amber-200 bg-amber-50 text-amber-900 hover:border-amber-300 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100' => $a->priority === \App\Enums\AnnouncementPriority::Important,
                                       'border-zinc-200 bg-zinc-50 text-zinc-800 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100' => $a->priority === \App\Enums\AnnouncementPriority::Normal])>
                                <flux:icon :name="$a->priority->icon()" variant="mini" class="shrink-0" />
                                <span class="min-w-0 flex-1 truncate font-medium">{{ $a->title }}</span>
                                <span class="shrink-0 text-xs opacity-70">{{ $a->published_at->isToday() ? $a->published_at->format('H:i') : $a->published_at->translatedFormat('j M') }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <x-calendar.agenda :items="$this->today" :empty="__('Na dziś nic nie zaplanowano. Sprawdź kalendarz poniżej.')" />
            </section>

            <div class="min-w-0 space-y-6 xl:col-span-5">
                @feature('workflows')
                <section class="{{ $card }}" data-dashboard-decisions>
                    <div class="{{ $cardHead }}">
                        <h2 class="{{ $h2 }}"><flux:icon name="hand-raised" variant="mini" class="text-sky-500" /> {{ __('Do mojej decyzji') }}</h2>
                        <a href="{{ route('workflows.index') }}" wire:navigate class="{{ $more }}">{{ __('Obiegi') }} <flux:icon name="arrow-right" variant="micro" /></a>
                    </div>
                    <div class="-mx-2 space-y-0.5">
                        @forelse ($this->decisions as $r)
                            <a href="{{ route('workflows.show', $r) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
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
                            <p class="px-2 py-1 text-sm text-zinc-500">{{ __('Nic nie czeka na Twoją decyzję.') }}</p>
                        @endforelse
                    </div>
                </section>
                @endfeature

                @feature('tasks')
                <section class="{{ $card }}" data-dashboard-tasks>
                    <div class="{{ $cardHead }}">
                        <h2 class="{{ $h2 }}"><flux:icon name="check-circle" variant="mini" class="text-emerald-500" /> {{ __('Moje zadania') }}</h2>
                        <a href="{{ route('tasks.index') }}" wire:navigate class="{{ $more }}">{{ __('Wszystkie zadania') }} <flux:icon name="arrow-right" variant="micro" /></a>
                    </div>
                    <div class="-mx-2 space-y-0.5">
                        @forelse ($this->myTasks as $t)
                            <a href="{{ route('tasks.show', $t) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
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
                            <p class="px-2 py-1 text-sm text-zinc-500">{{ __('Brak zadań na ten tydzień. Dobra robota!') }}</p>
                        @endforelse
                    </div>
                </section>
                @endfeature

                @feature('announcements')
                <section class="{{ $card }}" data-dashboard-announcements>
                    <div class="{{ $cardHead }}">
                        <h2 class="{{ $h2 }}"><flux:icon name="megaphone" variant="mini" class="text-primary" /> {{ __('Komunikaty szkoły') }}</h2>
                        <a href="{{ route('communication.index') }}" wire:navigate class="{{ $more }}">{{ __('Centrum komunikatów') }} <flux:icon name="arrow-right" variant="micro" /></a>
                    </div>
                    <div class="-mx-2 space-y-0.5">
                        @forelse ($this->latestAnnouncements as $a)
                            <a href="{{ route('communication.show', $a) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                <flux:icon :name="$a->priority->icon()" variant="mini" @class(['shrink-0', 'text-red-600' => $a->priority === \App\Enums\AnnouncementPriority::Urgent, 'text-amber-600' => $a->priority === \App\Enums\AnnouncementPriority::Important, 'text-zinc-400' => $a->priority === \App\Enums\AnnouncementPriority::Normal]) />
                                <span class="min-w-0 flex-1">
                                    <span @class(['block truncate text-sm', 'font-semibold' => ! $a->pivot->read_at])>{{ $a->title }}</span>
                                    <span class="block truncate text-xs text-zinc-500">{{ $a->author?->name }} · {{ $a->published_at->diffForHumans() }}</span>
                                </span>
                                @if (! $a->pivot->read_at)
                                    <span class="size-2 shrink-0 rounded-full bg-primary" title="{{ __('Nieprzeczytany') }}"></span>
                                @endif
                            </a>
                        @empty
                            <p class="px-2 py-1 text-sm text-zinc-500">{{ __('Nie masz jeszcze komunikatów.') }}</p>
                        @endforelse
                    </div>
                </section>
                @endfeature
            </div>
        </div>

        {{-- 2. The month --}}
        <section class="{{ $card }}" aria-label="{{ __('Kalendarz') }}" data-dashboard-calendar>
            <x-calendar.month :month="$this->currentMonth" :days="$this->monthDays">
                <x-slot:actions>
                    @if ($this->mode !== $this->defaultMode)
                        <flux:button size="xs" variant="ghost" icon="bookmark" wire:click="rememberCalendar">{{ __('Ustaw jako domyślny') }}</flux:button>
                    @endif
                    <div class="inline-flex rounded-mci bg-zinc-100 p-0.5 text-sm dark:bg-zinc-800" role="radiogroup" aria-label="{{ __('Widok kalendarza') }}" data-calendar-mode="{{ $this->mode }}">
                        @foreach (['personal' => ['user', __('Mój kalendarz'), __('Mój')], 'school' => ['building-library', __('Kalendarz szkoły'), __('Szkoła')]] as $key => [$icon, $label, $short])
                            <button type="button" role="radio" aria-checked="{{ $this->mode === $key ? 'true' : 'false' }}" wire:click="setCalendar('{{ $key }}')"
                                @class(['inline-flex items-center gap-1.5 rounded-[calc(var(--mci-radius)-2px)] px-3 py-1 font-medium transition',
                                        'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' => $this->mode === $key,
                                        'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $this->mode !== $key])>
                                <flux:icon :name="$icon" variant="micro" /> <span class="sm:hidden">{{ $short }}</span><span class="hidden sm:inline">{{ $label }}</span>
                                @if ($this->defaultMode === $key)<span class="sr-only">({{ __('domyślny') }})</span>@endif
                            </button>
                        @endforeach
                    </div>
                </x-slot:actions>
            </x-calendar.month>
            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-primary"></span> {{ __('Wydarzenie publiczne') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-sky-500"></span> {{ __('Wydarzenie szkolne') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-violet-500"></span> {{ __('Międzyszkolne') }}</span>
                @if ($this->mode === 'personal')
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-amber-500"></span> {{ __('Moje zadanie') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-red-500"></span> {{ __('Termin / po terminie') }}</span>
                @endif
                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span> {{ __('Kamień milowy') }}</span>
            </div>
        </section>

        {{-- 3. Projects over time --}}
        @feature('projects')
        <section class="{{ $card }}" aria-labelledby="timeline-h" data-dashboard-timeline>
            <div class="{{ $cardHead }}">
                <h2 id="timeline-h" class="{{ $h2 }}"><flux:icon name="rectangle-stack" variant="mini" class="text-violet-500" /> {{ __('Projekty w czasie') }}</h2>
                <span class="text-sm text-zinc-500">{{ $this->mode === 'personal' ? __('moje projekty') : __('projekty szkoły') }} · {{ $this->window['from']->translatedFormat('j M') }} – {{ $this->window['to']->translatedFormat('j M Y') }}</span>
                <a href="{{ route('projects.index') }}" wire:navigate class="{{ $more }}">{{ __('Wszystkie projekty') }} <flux:icon name="arrow-right" variant="micro" /></a>
            </div>
            <x-calendar.timeline :projects="$this->timeline" :from="$this->window['from']" :to="$this->window['to']" />
        </section>
        @endfeature

        {{-- 4. More --}}
        <div class="grid items-start gap-6 lg:grid-cols-2 xl:grid-cols-3">
            @feature('events')
            <section class="{{ $card }} min-w-0" data-dashboard-events>
                <div class="{{ $cardHead }}">
                    <h2 class="{{ $h2 }}"><flux:icon name="calendar-days" variant="mini" class="text-zinc-400" /> {{ __('Najbliższe wydarzenia') }}</h2>
                    <a href="{{ route('events.index') }}" wire:navigate class="{{ $more }}">{{ __('Wszystkie') }} <flux:icon name="arrow-right" variant="micro" /></a>
                </div>
                <div class="-mx-2 space-y-0.5">
                    @forelse ($this->upcomingEvents as $event)
                        <a href="{{ route('events.show', $event) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
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
                        <p class="px-2 py-1 text-sm text-zinc-500">{{ __('Brak zaplanowanych wydarzeń.') }}</p>
                    @endforelse
                </div>
            </section>
            @endfeature

            @feature('workflows')
            <section class="{{ $card }} min-w-0" data-dashboard-requests>
                <div class="{{ $cardHead }}">
                    <h2 class="{{ $h2 }}"><flux:icon name="paper-airplane" variant="mini" class="text-zinc-400" /> {{ __('Moje wnioski w toku') }}</h2>
                    <a href="{{ route('workflows.create') }}" wire:navigate class="{{ $more }}"><flux:icon name="plus" variant="micro" /> {{ __('Nowy wniosek') }}</a>
                </div>
                <div class="-mx-2 space-y-0.5">
                    @forelse ($this->myRequests as $r)
                        <a href="{{ route('workflows.show', $r) }}" wire:navigate class="flex items-center gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                            <flux:icon :name="$r->status->icon()" variant="mini" class="shrink-0 text-zinc-400" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $r->title }}</span>
                                <span class="block truncate text-xs text-zinc-500">{{ $r->currentStage()['name'] ?? $r->template_name }}</span>
                            </span>
                            <flux:badge size="sm" :color="$r->status->color()">{{ $r->status->label() }}</flux:badge>
                        </a>
                    @empty
                        <p class="px-2 py-1 text-sm text-zinc-500">{{ __('Nie masz otwartych wniosków.') }}</p>
                    @endforelse
                </div>
            </section>
            @endfeature

            <section class="{{ $card }} min-w-0">
                <div class="{{ $cardHead }}">
                    <h2 class="{{ $h2 }}"><flux:icon name="user-group" variant="mini" class="text-zinc-400" /> {{ __('Moje grupy') }}</h2>
                </div>
                <div class="-mx-2 space-y-0.5">
                    @forelse ($this->myGroups as $group)
                        <a href="{{ route('groups.show', $group) }}" wire:navigate
                           class="flex items-center justify-between gap-3 rounded-mci px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
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
                </div>
            </section>
        </div>
    @else
        <x-ui.empty-state icon="building-library" :title="__('Nie należysz jeszcze do żadnej szkoły')"
                          :description="__('Poproś administratora o zaproszenie albo użyj kodu z plakatu.')" />
    @endif

    @if ($this->adminStats)
        <section class="space-y-3" aria-labelledby="admin-stats">
            <h2 id="admin-stats" class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Administracja szkoły') }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-5">
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
