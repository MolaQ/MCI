<div class="space-y-6">
    <x-ui.page-header :title="__('Projekty')" :subtitle="__('Projekty szkolne i międzyszkolne: zespół, kamienie milowe, zadania i wnioski w jednym miejscu.')">
        @if ($canCreate)
            <x-slot:actions>
                <flux:button :href="route('projects.create')" wire:navigate variant="primary" icon="plus">{{ __('Nowy projekt') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="{{ __('Filtry projektów') }}">
            <x-ui.pill wire:click="setFilter('view', 'mine')" :active="$view === 'mine'" :count="$this->counts['mine']"><flux:icon name="user" variant="micro" /> {{ __('Moje projekty') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('view', 'coordinated')" :active="$view === 'coordinated'" :count="$this->counts['coordinated']"><flux:icon name="flag" variant="micro" /> {{ __('Koordynowane przeze mnie') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('view', 'all')" :active="$view === 'all'"><flux:icon name="rectangle-stack" variant="micro" /> {{ __('Wszystkie') }}</x-ui.pill>
            <span class="mx-1 hidden h-5 w-px bg-zinc-200 sm:block dark:bg-zinc-700"></span>
            @foreach ($statuses as $s)
                @continue($s === \App\Enums\ProjectStatus::Cancelled && $status !== 'cancelled')
                <x-ui.pill wire:click="setFilter('status', '{{ $status === $s->value ? '' : $s->value }}')" :active="$status === $s->value" data-status-pill="{{ $s->value }}">
                    <flux:icon :name="$s->icon()" variant="micro" /> {{ $s->value === 'completed' ? __('Zakończone') : ($s->value === 'planned' ? __('Planowane') : ($s->value === 'on_hold' ? __('Wstrzymane') : $s->label())) }}
                </x-ui.pill>
            @endforeach
            @if ($this->schools->count() > 1)
                <flux:dropdown>
                    <x-ui.pill :active="$school !== ''">
                        <flux:icon name="building-library" variant="micro" />
                        {{ $this->schools->firstWhere('slug', $school)?->short_name ?? __('Każda szkoła') }}
                        <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                    </x-ui.pill>
                    <flux:menu>
                        <flux:menu.item wire:click="setFilter('school', '')">{{ __('Każda szkoła') }}</flux:menu.item>
                        <flux:menu.separator />
                        @foreach ($this->schools as $s)
                            <flux:menu.item icon="building-library" wire:click="setFilter('school', '{{ $s->slug }}')">{{ $s->name }}</flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" wire:loading.class="opacity-60" wire:target="setFilter">
        @forelse ($this->projects as $p)
            @php($progress = $p->progress())
            @php($next = $p->milestones->whereNull('completed_at')->whereNotNull('due_at')->sortBy('due_at')->first())
            <a href="{{ route('projects.show', $p) }}" wire:navigate wire:key="p-{{ $p->id }}" data-project
               class="group flex h-full flex-col overflow-hidden rounded-mci border border-zinc-200 bg-white transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                @if ($p->cover)
                    <img src="{{ $p->cover->url() }}" alt="" loading="lazy" class="aspect-[3/1] w-full object-cover" />
                @endif
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-mci bg-primary/10 text-primary"><flux:icon :name="$p->kind->icon()" variant="mini" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="line-clamp-2 font-semibold text-zinc-900 group-hover:text-primary dark:text-white">{{ $p->name }}</div>
                            <div class="flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
                                <span>{{ $p->kind->label() }}</span>
                                <span>· {{ $p->school->short_name }}</span>
                                @if ($p->partner_schools_count)
                                    <span class="inline-flex items-center gap-0.5">· <flux:icon name="arrows-right-left" variant="micro" /> {{ trans_choice('+:count szkoła|+:count szkoły|+:count szkół', $p->partner_schools_count) }}</span>
                                @endif
                            </div>
                        </div>
                        <flux:badge size="sm" :color="$p->status->color()">{{ $p->status->label() }}</flux:badge>
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-xs text-zinc-500">
                            <span>{{ __('Postęp') }}</span><span class="tabular-nums">{{ $progress['percent'] }}%</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-primary" style="width: {{ $progress['percent'] }}%"></div>
                        </div>
                    </div>

                    <div class="mt-auto space-y-1 text-xs text-zinc-500">
                        @if ($next)
                            <div @class(['flex items-center gap-1.5', 'font-medium text-red-600' => $next->isOverdue()])><flux:icon name="flag" variant="micro" /> <span class="truncate">{{ $next->title }}</span> <span class="ms-auto shrink-0">{{ $next->due_at->translatedFormat('j M') }}</span></div>
                        @endif
                        <div class="flex items-center gap-1.5"><flux:icon name="user-circle" variant="micro" /> <span class="truncate">{{ $p->coordinator?->name ?? __('bez koordynatora') }}</span>
                            @if ($p->datesLabel())<span class="ms-auto shrink-0">{{ $p->datesLabel() }}</span>@endif
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="sm:col-span-2 xl:col-span-3 2xl:col-span-4">
                <x-ui.empty-state icon="rectangle-stack" :title="__('Brak projektów dla tych filtrów')"
                                  :description="$canCreate ? __('Załóż projekt: wydarzenie, grant, Erasmus+, inwestycję lub konkurs.') : __('Gdy ktoś doda Cię do zespołu projektu, zobaczysz go tutaj.')" />
            </div>
        @endforelse
    </div>
</div>
