<div x-data="mciFullscreen" x-on:keydown.escape.window="if (! active) window.location = @js(\App\Livewire\Publicly\Home::url())"
     x-on:keydown.f.window="toggle()" class="h-full" wire:poll.{{ $pollSeconds }}s>
    {{-- TV-safe margins (about 4% of the screen) and type that scales with the screen. --}}
    <div x-data="mciTvRotator(@js($screens))" class="flex h-full flex-col gap-[2.5vh] px-[4vw] py-[4vh] text-[clamp(1rem,1.15vw,2.25rem)]" data-tv-board>
        <header class="flex items-center justify-between gap-[2vw]">
            <div class="flex min-w-0 items-center gap-[1.2vw]">
                @if ($currentSchool)
                    <x-app.school-badge :school="$currentSchool" class="!size-[4.2vw] !min-h-14 !min-w-14 !text-[1.1vw]" />
                @else
                    <span class="flex size-[4.2vw] min-h-14 min-w-14 items-center justify-center rounded-2xl bg-primary text-[1.2vw] font-bold text-primary-foreground">{{ $appearance->shortName() }}</span>
                @endif
                <div class="min-w-0">
                    <div class="truncate text-[2.2em] font-bold leading-tight">{{ $currentSchool?->name ?? $appearance->schoolName() }}</div>
                    <div class="flex items-center gap-3 text-[1.1em] text-zinc-400">
                        @foreach ($screens as $s)
                            <span x-show="current === @js($s['key'])" @if (! $loop->first) x-cloak @endif class="inline-flex items-center gap-2">
                                <flux:icon :name="$catalog[$s['key']]['icon']" class="size-[1.2em]" /> {{ $catalog[$s['key']]['label'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-[1.5vw]" x-data="mciClock">
                <div class="text-end">
                    <div class="text-[3.6em] font-semibold leading-none tabular-nums" x-text="time"></div>
                    <div class="mt-1 text-[1.1em] text-zinc-400 first-letter:uppercase" x-text="date"></div>
                </div>
                <div class="flex flex-col gap-2 opacity-60 transition hover:opacity-100">
                    <button type="button" x-on:click="toggle()" class="flex size-11 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20" :aria-label="active ? @js(__('Wyjdź z pełnego ekranu')) : @js(__('Pełny ekran'))">
                        <flux:icon x-show="! active" name="arrows-pointing-out" />
                        <flux:icon x-show="active" x-cloak name="arrows-pointing-in" />
                    </button>
                    <a href="{{ \App\Livewire\Publicly\Home::url() }}" class="flex size-11 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20" aria-label="{{ __('Zamknij tryb TV') }}" title="{{ __('Zamknij (Esc)') }}">
                        <flux:icon name="x-mark" />
                    </a>
                </div>
            </div>
        </header>

        {{-- Urgent / pinned public announcements stay visible on every screen. --}}
        @if ($alerts->contains(fn ($a) => $a->priority === \App\Enums\AnnouncementPriority::Urgent))
            @php($urgent = $alerts->first(fn ($a) => $a->priority === \App\Enums\AnnouncementPriority::Urgent))
            <div class="flex items-center gap-[1vw] rounded-2xl bg-red-600 px-[1.5vw] py-[1.2vh] text-[1.3em] font-semibold text-white" role="alert" data-tv-alert>
                <flux:icon name="exclamation-triangle" class="size-[1.4em] shrink-0" />
                <span class="truncate">{{ $urgent->title }}</span>
            </div>
        @endif

        <main class="relative min-h-0 flex-1">
            {{-- 1. Today --}}
            @if (collect($screens)->contains('key', 'agenda'))
                <section x-show="current === 'agenda'" x-transition.opacity.duration.500ms class="absolute inset-0 grid gap-[2vw] lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]" data-tv-screen="agenda">
                    <div class="flex min-h-0 flex-col gap-[1.5vh]">
                        <h2 class="text-[1.5em] font-semibold text-zinc-300">{{ __('Dziś w szkole') }}</h2>
                        @forelse ($todayItems->take(6) as $item)
                            <div wire:key="tv-t-{{ $item->key() }}" @class(['flex items-center gap-[1.5vw] rounded-2xl px-[1.5vw] py-[1.6vh]',
                                'bg-primary/90 text-primary-foreground' => $item->kind === 'event' && ! $item->isOver(),
                                'bg-white/5 text-zinc-300' => $item->kind !== 'event' || $item->isOver()])>
                                <div class="w-[9vw] shrink-0 text-[1.75em] font-bold leading-tight tabular-nums">{{ $item->timeLabel() }}</div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3">
                                        <span class="truncate text-[1.75em] font-semibold leading-tight">{{ $item->title }}</span>
                                        @if ($item->isNow())
                                            <span class="shrink-0 rounded-full bg-white/25 px-3 py-0.5 text-[0.8em] font-bold uppercase tracking-wide">{{ __('Teraz') }}</span>
                                        @endif
                                    </div>
                                    @if ($item->meta)
                                        <div class="truncate text-[1.1em] opacity-80">{{ $item->meta }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl bg-white/5 px-[1.5vw] py-[3vh] text-[1.5em] text-zinc-400">{{ __('Dziś brak wydarzeń publicznych.') }}</div>
                        @endforelse
                    </div>
                    <div class="flex min-h-0 flex-col gap-[1.5vh]">
                        {{-- The urgent one is already in the red bar above. --}}
                        @php($important = $alerts->reject(fn ($a) => $a->priority === \App\Enums\AnnouncementPriority::Urgent))
                        @if ($important->isNotEmpty())
                            <h2 class="text-[1.5em] font-semibold text-zinc-300">{{ __('Ważne') }}</h2>
                            @foreach ($important as $a)
                                <div wire:key="tv-al-{{ $a->id }}" @class(['rounded-2xl px-[1.3vw] py-[1.4vh]', 'bg-red-500/20 ring-1 ring-red-400/50' => $a->priority === \App\Enums\AnnouncementPriority::Urgent, 'bg-amber-500/15 ring-1 ring-amber-400/40' => $a->priority !== \App\Enums\AnnouncementPriority::Urgent])>
                                    <div class="text-[1.35em] font-semibold leading-tight">{{ $a->title }}</div>
                                    <p class="mt-1 line-clamp-2 text-[1em] text-zinc-300">{{ $excerpt($a->body, 160) }}</p>
                                </div>
                            @endforeach
                        @endif
                        <h2 class="text-[1.5em] font-semibold text-zinc-300">{{ __('W najbliższych dniach') }}</h2>
                        @forelse ($upcoming as $event)
                            <div wire:key="tv-u-{{ $event->id }}" class="flex items-center gap-[1vw] rounded-2xl bg-white/5 px-[1vw] py-[1.1vh]">
                                <div class="flex w-[4.5vw] min-w-16 shrink-0 flex-col items-center rounded-xl bg-white/10 py-[0.6vh]">
                                    <span class="text-[0.8em] uppercase text-zinc-400">{{ $event->starts_at->translatedFormat('M') }}</span>
                                    <span class="text-[1.8em] font-bold leading-none tabular-nums">{{ $event->starts_at->format('j') }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-[1.3em] font-semibold">{{ $event->title }}</div>
                                    <div class="truncate text-[0.95em] text-zinc-400">{{ collect([$event->starts_at->translatedFormat('l'), $event->all_day ? null : $event->starts_at->format('H:i'), $event->location])->filter()->implode(' · ') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl bg-white/5 px-[1vw] py-[2vh] text-[1.2em] text-zinc-400">{{ __('Brak zaplanowanych wydarzeń.') }}</div>
                        @endforelse
                    </div>
                </section>
            @endif

            {{-- 2. The month --}}
            @if (collect($screens)->contains('key', 'calendar'))
                @php($weeks = \App\Domain\Calendar\Agenda::monthGrid($month))
                <section x-show="current === 'calendar'" x-cloak x-transition.opacity.duration.500ms class="absolute inset-0 flex flex-col gap-[1.5vh]" data-tv-screen="calendar">
                    <h2 class="text-[1.8em] font-semibold first-letter:uppercase">{{ $month->translatedFormat('F Y') }}</h2>
                    <div class="grid grid-cols-7 gap-[0.4vw] text-center text-[0.95em] font-semibold uppercase tracking-wider text-zinc-500">
                        @foreach ([__('Pn'), __('Wt'), __('Śr'), __('Cz'), __('Pt'), __('So'), __('Nd')] as $n)
                            <div>{{ $n }}</div>
                        @endforeach
                    </div>
                    <div class="grid min-h-0 flex-1 grid-cols-7 gap-[0.4vw]" style="grid-template-rows: repeat({{ count($weeks) }}, minmax(0, 1fr))">
                        @foreach ($weeks as $week)
                            @foreach ($week as $d)
                                @php($dayItems = $monthDays[$d->toDateString()] ?? collect())
                                <div @class(['flex min-h-0 flex-col gap-[0.3vh] overflow-hidden rounded-xl px-[0.5vw] py-[0.4vw]',
                                    'bg-white/[0.06]' => $d->isSameMonth($month) && ! $d->isToday(),
                                    'bg-white/[0.02] text-zinc-600' => ! $d->isSameMonth($month),
                                    'bg-primary/25 ring-2 ring-primary' => $d->isToday()])>
                                    <span class="flex shrink-0 items-baseline justify-between gap-2">
                                        <span @class(['text-[1.05em] font-bold leading-none tabular-nums', 'text-zinc-500' => $d->isWeekend() && ! $d->isToday()])>{{ $d->day }}</span>
                                        @if ($dayItems->count() > 2)
                                            <span class="text-[0.75em] text-zinc-400">+{{ $dayItems->count() - 2 }}</span>
                                        @endif
                                    </span>
                                    @foreach ($dayItems->take(2) as $item)
                                        <span class="shrink-0 truncate rounded-md px-[0.4vw] py-[0.15vh] text-[0.8em] font-medium leading-snug {{ $item->kind === 'milestone' ? 'bg-emerald-500/25 text-emerald-100' : 'bg-primary/40 text-white' }}">{{ $item->title }}</span>
                                    @endforeach
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- 3. Projects over time --}}
            @if (collect($screens)->contains('key', 'projects'))
                <section x-show="current === 'projects'" x-cloak x-transition.opacity.duration.500ms class="absolute inset-0 flex flex-col gap-[2vh]" data-tv-screen="projects">
                    <h2 class="text-[1.8em] font-semibold">{{ __('Projekty w czasie') }} <span class="text-[0.6em] font-normal text-zinc-400">{{ $from->translatedFormat('j M') }} – {{ $to->translatedFormat('j M Y') }}</span></h2>
                    <x-calendar.timeline :projects="$projects->take(8)" :from="$from" :to="$to" :links="false" tv class="min-h-0 flex-1" />
                </section>
            @endif

            {{-- 4. Announcements --}}
            @if (collect($screens)->contains('key', 'announcements'))
                <section x-show="current === 'announcements'" x-cloak x-transition.opacity.duration.500ms class="absolute inset-0 flex flex-col gap-[2vh]" data-tv-screen="announcements">
                    <h2 class="text-[1.8em] font-semibold">{{ __('Komunikaty') }}</h2>
                    <div class="grid min-h-0 flex-1 auto-rows-fr gap-[1.2vw] md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($announcements as $a)
                            <article wire:key="tv-a-{{ $a->id }}" @class(['flex min-h-0 flex-col gap-[1vh] overflow-hidden rounded-2xl p-[1.3vw]',
                                'bg-red-500/20 ring-1 ring-red-400/50' => $a->priority === \App\Enums\AnnouncementPriority::Urgent,
                                'bg-amber-500/15 ring-1 ring-amber-400/40' => $a->priority === \App\Enums\AnnouncementPriority::Important,
                                'bg-white/[0.06]' => $a->priority === \App\Enums\AnnouncementPriority::Normal])>
                                <div class="text-[0.95em] text-zinc-400">{{ $currentSchool ? '' : $a->school->short_name.' · ' }}{{ $a->published_at->translatedFormat('j F') }}</div>
                                <div class="text-[1.5em] font-semibold leading-tight">{{ $a->title }}</div>
                                <p class="line-clamp-4 text-[1.1em] text-zinc-300">{{ $excerpt($a->body) }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </main>

        <footer class="flex items-center gap-[1.5vw] text-[0.9em] text-zinc-500">
            @if (count($screens) > 1)
                <div class="flex flex-1 items-center gap-[0.6vw]" role="tablist" aria-label="{{ __('Ekrany') }}">
                    @foreach ($screens as $i => $s)
                        <button type="button" x-on:click="go({{ $i }})" role="tab" :aria-selected="index === {{ $i }}"
                                class="group flex min-w-0 flex-1 flex-col gap-1 text-start">
                            <span class="truncate transition" :class="index === {{ $i }} ? 'text-zinc-200' : ''">{{ $catalog[$s['key']]['label'] }} · {{ $s['seconds'] }} s</span>
                            <span class="h-1 overflow-hidden rounded-full bg-white/10">
                                <span class="block h-full rounded-full bg-primary transition-[width] duration-300 ease-linear" :style="`width: ${index === {{ $i }} ? progress : (index > {{ $i }} ? 100 : 0)}%`"></span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @else
                <span class="flex-1">{{ __('Odświeżanie automatyczne') }}</span>
            @endif
            <span class="shrink-0" x-text="paused ? @js(__('Wstrzymano — spacja wznawia')) : @js(__('← → ekrany · spacja pauza · F pełny ekran · Esc wyjście'))"></span>
        </footer>
    </div>
</div>
