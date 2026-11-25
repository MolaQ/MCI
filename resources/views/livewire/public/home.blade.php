@php($wrap = 'mx-auto w-full max-w-7xl px-4 sm:px-6 2xl:max-w-[100rem]')
<div>
    {{-- 1. Hero: identity, live summary, quick actions --}}
    <section class="relative overflow-hidden border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="mci-title">
        <div class="pointer-events-none absolute inset-0 opacity-60 [background-image:radial-gradient(var(--color-zinc-200)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(to_bottom,black,transparent)] dark:[background-image:radial-gradient(var(--color-zinc-800)_1px,transparent_1px)]"></div>
        <div class="{{ $wrap }} relative grid gap-10 py-10 sm:py-14 lg:grid-cols-12 lg:items-center lg:py-16">
            <div class="mci-fade-up space-y-6 lg:col-span-7">
                <p class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white/80 px-3 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-300">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full rounded-full bg-primary opacity-60 motion-safe:animate-ping"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-primary"></span>
                    </span>
                    {{ __('Centrum informacji · stan na :time', ['time' => now()->format('H:i')]) }}
                </p>
                <div class="space-y-3">
                    <h1 id="mci-title" class="text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl lg:text-5xl dark:text-white">{{ $appearance->schoolName() }}</h1>
                    <p class="max-w-2xl text-base text-pretty text-zinc-600 sm:text-lg dark:text-zinc-300">{{ __('Wydarzenia, oferta i komunikaty szkół w jednym miejscu. Bez logowania.') }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="{{ __('Podsumowanie') }}">
                    @foreach ([
                        ['calendar', __('Dziś'), $this->stats['today'], '#wydarzenia', 'today'],
                        ['calendar-days', __('Ten tydzień'), $this->stats['week'], '#wydarzenia', 'week'],
                        ['building-library', __('Szkoły'), $this->stats['schools'], '#szkoly', null],
                        ['megaphone', __('Komunikaty'), $this->stats['announcements'], '#wyroznione', null],
                    ] as $i => [$icon, $statLabel, $value, $anchor, $preset])
                        <a href="{{ $anchor }}" @if ($preset) wire:click="setFilter('when', '{{ $preset }}')" @endif
                           class="mci-fade-up group flex flex-col gap-1 rounded-mci border border-zinc-200 bg-white p-3 transition duration-150 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-zinc-600"
                           style="animation-delay: {{ 60 + $i * 40 }}ms">
                            <dt class="flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400"><flux:icon :name="$icon" variant="micro" /> {{ $statLabel }}</dt>
                            <dd class="text-2xl font-semibold tabular-nums text-zinc-900 group-hover:text-primary dark:text-white">{{ $value }}</dd>
                        </a>
                    @endforeach
                </dl>
            </div>

            <nav class="mci-fade-up grid gap-3 lg:col-span-5" style="animation-delay: 120ms" aria-label="{{ __('Szybkie działania') }}">
                @auth
                    <a href="{{ route('dashboard') }}" class="group flex items-center gap-4 rounded-mci bg-primary p-4 text-primary-foreground shadow-sm transition duration-150 hover:-translate-y-0.5 hover:shadow-md">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-mci bg-white/15"><flux:icon name="squares-2x2" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-semibold">{{ __('Mój pulpit') }}</span><span class="block text-sm opacity-80">{{ __('Komunikaty, grupy i wydarzenia wewnętrzne') }}</span></span>
                        <flux:icon name="arrow-right" variant="mini" class="shrink-0 transition-transform group-hover:translate-x-0.5" />
                    </a>
                @else
                    <a href="{{ route('login') }}" class="group flex items-center gap-4 rounded-mci bg-primary p-4 text-primary-foreground shadow-sm transition duration-150 hover:-translate-y-0.5 hover:shadow-md">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-mci bg-white/15"><flux:icon name="arrow-right-end-on-rectangle" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-semibold">{{ __('Zaloguj się') }}</span><span class="block text-sm opacity-80">{{ __('Dla pracowników szkół') }}</span></span>
                        <flux:icon name="arrow-right" variant="mini" class="shrink-0 transition-transform group-hover:translate-x-0.5" />
                    </a>
                @endauth

                <flux:modal.trigger name="join">
                    <button type="button" class="group flex w-full items-center gap-4 rounded-mci border border-zinc-200 bg-white p-4 text-start transition duration-150 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-zinc-600">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-mci bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200"><flux:icon name="qr-code" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-zinc-900 dark:text-white">{{ __('Dołącz z zaproszenia') }}</span><span class="block text-sm text-zinc-500">{{ __('Kod z plakatu, e-maila lub skan QR') }}</span></span>
                        <flux:icon name="arrow-right" variant="mini" class="shrink-0 text-zinc-400 transition-transform group-hover:translate-x-0.5" />
                    </button>
                </flux:modal.trigger>

                @if ($tvEnabled)
                    <a href="{{ route('tv', array_filter(['szkola' => $school])) }}" class="group flex items-center gap-4 rounded-mci border border-zinc-200 bg-white p-4 transition duration-150 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-zinc-600">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-mci bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200"><flux:icon name="tv" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-zinc-900 dark:text-white">{{ __('Tryb TV') }}</span><span class="block text-sm text-zinc-500">{{ __('Pełny ekran dla telewizora na korytarzu') }}</span></span>
                        <flux:icon name="arrow-right" variant="mini" class="shrink-0 text-zinc-400 transition-transform group-hover:translate-x-0.5" />
                    </a>
                @endif
            </nav>
        </div>
    </section>

    {{-- 2. One row of pill filters (school · category · time) --}}
    <div class="border-b border-zinc-200 bg-zinc-50/80 dark:border-zinc-800 dark:bg-zinc-900/60">
        <div class="{{ $wrap }} py-3">
            <div class="mci-scroll-x -mx-1 flex items-center gap-2 overflow-x-auto px-1 py-0.5" role="toolbar" aria-label="{{ __('Filtry') }}">
                @php($currentSchool = $this->schools->firstWhere('slug', $school))
                <flux:dropdown position="bottom" align="start" class="shrink-0">
                    <x-ui.pill :active="$school !== ''" class="shrink-0" data-filter="school">
                        <flux:icon name="building-library" variant="micro" />
                        {{ $currentSchool?->short_name ?? __('Wszystkie szkoły') }}
                        <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                    </x-ui.pill>
                    <flux:menu class="max-h-80 min-w-56 overflow-y-auto">
                        <flux:menu.radio.group wire:model.live="school">
                            <flux:menu.radio value="">{{ __('Wszystkie szkoły') }}</flux:menu.radio>
                            @foreach ($this->schools as $s)
                                <flux:menu.radio :value="$s->slug" wire:key="fs-{{ $s->id }}">{{ $s->short_name }} <span class="ms-auto ps-3 text-xs tabular-nums text-zinc-400">{{ $s->upcoming_count }}</span></flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>

                @php($currentCategory = $this->categories->firstWhere('id', (int) $category))
                <flux:dropdown position="bottom" align="start" class="shrink-0">
                    <x-ui.pill :active="$category !== ''" class="shrink-0" data-filter="category">
                        <flux:icon :name="$currentCategory?->icon() ?? 'tag'" variant="micro" />
                        {{ $currentCategory?->name ?? __('Każda kategoria') }}
                        <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                    </x-ui.pill>
                    <flux:menu class="max-h-80 min-w-56 overflow-y-auto">
                        <flux:menu.radio.group wire:model.live="category">
                            <flux:menu.radio value="">{{ __('Każda kategoria') }}</flux:menu.radio>
                            @foreach ($this->categories as $c)
                                <flux:menu.radio :value="(string) $c->id" wire:key="fc-{{ $c->id }}">
                                    <flux:icon :name="$c->icon()" variant="micro" class="me-1.5 text-zinc-400" /> {{ $c->name }}
                                    <span class="ms-auto ps-3 text-xs tabular-nums text-zinc-400">{{ $c->upcoming_count }}</span>
                                </flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>

                <span class="mx-1 h-5 w-px shrink-0 bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>

                @foreach ($whenOptions as $key => $label)
                    <x-ui.pill wire:click="setFilter('when', '{{ $key }}')" :active="$when === $key" class="shrink-0" wire:key="fw-{{ $key }}">{{ $label }}</x-ui.pill>
                @endforeach

                @if ($filtered)
                    <button type="button" wire:click="clearFilters" class="ms-auto inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1 text-sm text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200">
                        <flux:icon name="x-mark" variant="micro" /> {{ __('Wyczyść') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- 3. Featured bento: highlighted events + pinned public announcements --}}
    @php($lead = $this->featured->first())
    @php($annSlots = $lead ? max(0, 4 - ($this->featured->count() - 1)) : 4)
    @php($bentoAnnouncements = $this->pinned->take($annSlots))
    @php($moreAnnouncements = $this->pinned->slice($annSlots)->take(4))
    @if ($lead || $bentoAnnouncements->isNotEmpty())
        <section id="wyroznione" class="{{ $wrap }} scroll-mt-20 space-y-4 pt-10" aria-labelledby="h-featured" wire:loading.class="opacity-60" wire:target="school,category,setFilter,clearFilters">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <h2 id="h-featured" class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl dark:text-white">{{ __('Wyróżnione') }}</h2>
                    <p class="text-sm text-zinc-500">{{ __('Dni otwarte, konkursy, rekrutacja i najważniejsze komunikaty.') }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:auto-rows-[minmax(10.5rem,auto)] lg:grid-cols-4">
                @if ($lead)
                    @php($cover = $lead->cover?->url())
                    @php($beside = $this->featured->count() - 1 + $bentoAnnouncements->count())
                    <a href="{{ route('public.event', $lead) }}" wire:key="lead-{{ $lead->id }}"
                       @class([
                           'mci-fade-up group relative flex min-h-72 flex-col justify-end overflow-hidden rounded-mci border border-zinc-200 bg-zinc-900 p-6 text-white transition duration-200 hover:shadow-lg md:col-span-2 dark:border-zinc-700',
                           'lg:col-span-4 lg:min-h-80' => $beside === 0,
                           'lg:row-span-2' => $beside >= 3,
                       ])>
                        @if ($cover)
                            <img src="{{ $cover }}" alt="" class="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none" loading="lazy" />
                        @else
                            <div class="absolute inset-0 bg-primary"></div>
                            <flux:icon :name="$lead->category->icon()" class="absolute -end-6 -top-6 size-56 opacity-10" />
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-transparent"></div>

                        <div class="relative space-y-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-1 backdrop-blur"><flux:icon :name="$lead->category->icon()" variant="micro" /> {{ $lead->category->name }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-1 backdrop-blur">{{ $lead->school->short_name }}</span>
                            </div>
                            <h3 class="text-2xl font-semibold tracking-tight text-balance sm:text-3xl">{{ $lead->title }}</h3>
                            <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-white/85">
                                <span class="inline-flex items-center gap-1.5"><flux:icon name="calendar" variant="micro" /> {{ $lead->timeLabel() }}</span>
                                @if ($lead->location)
                                    <span class="inline-flex items-center gap-1.5"><flux:icon name="map-pin" variant="micro" /> {{ $lead->location }}</span>
                                @endif
                            </p>
                            @if ($lead->public_description)
                                <p class="line-clamp-2 max-w-xl text-sm text-white/75">{{ $lead->public_description }}</p>
                            @endif
                            <span class="inline-flex items-center gap-1 text-sm font-semibold">{{ __('Szczegóły') }} <flux:icon name="arrow-right" variant="micro" class="transition-transform group-hover:translate-x-0.5" /></span>
                        </div>
                    </a>
                @endif

                @foreach ($this->featured->slice(1) as $i => $e)
                    <a href="{{ route('public.event', $e) }}" wire:key="feat-{{ $e->id }}"
                       class="mci-fade-up group flex h-full flex-col gap-3 rounded-mci border border-zinc-200 bg-white p-4 transition duration-150 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/70"
                       style="animation-delay: {{ $i * 50 }}ms">
                        <div class="flex items-start justify-between gap-3">
                            <x-public.date-badge :date="$e->starts_at" />
                            <span class="inline-flex items-center gap-1 text-xs text-zinc-500"><flux:icon :name="$e->category->icon()" variant="micro" /> {{ $e->category->name }}</span>
                        </div>
                        <div class="font-semibold text-zinc-900 group-hover:text-primary dark:text-white">{{ $e->title }}</div>
                        <div class="mt-auto flex items-center gap-1.5 text-xs text-zinc-500"><x-app.school-badge :school="$e->school" size="sm" class="!size-4 !text-[0.4rem]" /> {{ $e->school->short_name }}</div>
                    </a>
                @endforeach

                @foreach ($bentoAnnouncements as $i => $a)
                    <a href="{{ route('public.announcement', $a) }}" wire:key="ann-{{ $a->id }}"
                       @class([
                           'mci-fade-up group flex h-full flex-col gap-2 rounded-mci border border-zinc-200 bg-white p-4 transition duration-150 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/70',
                           'md:col-span-2' => ! $lead && $loop->first,
                       ])
                       style="animation-delay: {{ ($i + 2) * 50 }}ms">
                        <div class="flex items-center gap-2 text-xs text-zinc-500">
                            <flux:icon :name="$a->isPinned() ? 'bookmark' : 'megaphone'" variant="micro" />
                            <span>{{ $a->isPinned() ? __('Przypięty') : __('Komunikat') }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ $a->school->short_name }}</span>
                            <span class="ms-auto tabular-nums">{{ $a->published_at->translatedFormat('j M') }}</span>
                        </div>
                        <div class="font-semibold text-zinc-900 group-hover:text-primary dark:text-white">{{ $a->title }}</div>
                        <p class="line-clamp-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $excerpt($a->body, 140) }}</p>
                        @if ($a->priority !== \App\Enums\AnnouncementPriority::Normal)
                            <div class="mt-auto"><flux:badge size="sm" :color="$a->priority->color()">{{ $a->priority->label() }}</flux:badge></div>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 4. Event list --}}
    <section id="wydarzenia" class="{{ $wrap }} scroll-mt-20 space-y-4 py-10" aria-labelledby="h-events">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="h-events" class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl dark:text-white">{{ __('Kalendarz wydarzeń') }}</h2>
                <p class="text-sm text-zinc-500">{{ __('Najbliższe terminy na górze.') }}</p>
            </div>
        </div>

        @php($events = $this->events)
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 transition-opacity duration-150" wire:loading.class="opacity-50" wire:target="school,category,setFilter,clearFilters,showMore">
            @forelse ($events->take($limit) as $event)
                <x-event-card :event="$event" :href="route('public.event', $event)" public wire:key="e-{{ $event->id }}" class="h-full" style="animation-delay: {{ ($loop->index % 6) * 40 }}ms" />
            @empty
                <div class="rounded-mci border border-dashed border-zinc-300 bg-white p-8 text-center md:col-span-2 lg:col-span-3 2xl:col-span-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <x-ui.empty-state icon="calendar" :title="$filtered ? __('Brak wydarzeń dla tych filtrów') : __('Na razie nic nie zaplanowano')"
                                      :description="$filtered ? __('Zmień szkołę, kategorię lub termin.') : __('Zajrzyj tu wkrótce.')" class="!py-2" />
                    @if ($filtered)
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters" class="mt-2">{{ __('Wyczyść filtry') }}</flux:button>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($events->count() > $limit)
            <div class="flex justify-center">
                <flux:button wire:click="showMore" icon="chevron-down" variant="ghost">{{ __('Pokaż więcej') }}</flux:button>
            </div>
        @endif
    </section>

    {{-- 5. School directory --}}
    <section id="szkoly" class="scroll-mt-20 border-y border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="h-schools">
        <div class="{{ $wrap }} space-y-4 py-10">
            <div>
                <h2 id="h-schools" class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl dark:text-white">{{ __('Szkoły i oferta') }}</h2>
                <p class="text-sm text-zinc-500">{{ __('Kierunki kształcenia, kontakt i kalendarz każdej szkoły.') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($this->schools as $s)
                    <article wire:key="s-{{ $s->id }}" class="mci-fade-up flex h-full flex-col gap-4 rounded-mci border border-zinc-200 p-5 transition duration-150 hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:hover:border-zinc-600" style="animation-delay: {{ $loop->index * 40 }}ms">
                        <div class="flex items-center gap-3">
                            <x-app.school-badge :school="$s" />
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-zinc-900 dark:text-white">{{ $s->name }}</h3>
                                <p class="text-sm text-zinc-500">{{ $s->city ?: $s->short_name }}</p>
                            </div>
                        </div>
                        <p class="line-clamp-2 min-h-10 text-sm text-zinc-600 dark:text-zinc-300">{{ $s->tagline ?: __('Poznaj ofertę i najbliższe wydarzenia szkoły.') }}</p>
                        <div class="flex gap-4 text-sm text-zinc-500">
                            @if ($offersEnabled($s))
                                <span class="inline-flex items-center gap-1"><flux:icon name="academic-cap" variant="micro" /> {{ trans_choice(':count kierunek|:count kierunki|:count kierunków', $s->offers_count) }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1"><flux:icon name="calendar" variant="micro" /> {{ trans_choice(':count wydarzenie|:count wydarzenia|:count wydarzeń', $s->upcoming_count) }}</span>
                        </div>
                        <div class="mt-auto flex items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                            <flux:button size="sm" variant="primary" icon="eye" wire:click="preview('{{ $s->slug }}')">{{ __('Podgląd') }}</flux:button>
                            <flux:button size="sm" variant="ghost" :href="route('public.school', $s->slug)">{{ __('Strona szkoły') }}</flux:button>
                            @if ($icsEnabled($s))
                                <flux:button size="sm" variant="ghost" icon="calendar-days" :href="route('public.school.ics', $s->slug)" class="ms-auto" :aria-label="__('Kalendarz :name (iCal)', ['name' => $s->short_name])" :tooltip="__('Dodaj kalendarz szkoły (iCal)')" />
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 6. More announcements (only what the bento did not show) --}}
    @if ($moreAnnouncements->isNotEmpty())
        <section id="komunikaty" class="{{ $wrap }} scroll-mt-20 space-y-4 py-10" aria-labelledby="h-news">
            <h2 id="h-news" class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl dark:text-white">{{ __('Więcej komunikatów') }}</h2>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($moreAnnouncements as $a)
                    <a href="{{ route('public.announcement', $a) }}" wire:key="more-{{ $a->id }}" class="mci-fade-up flex h-full gap-4 rounded-mci border border-zinc-200 bg-white p-4 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/70 dark:hover:bg-zinc-800">
                        <x-public.date-badge :date="$a->published_at" muted />
                        <div class="min-w-0 space-y-1">
                            <div class="text-xs text-zinc-500">{{ $a->school->short_name }}</div>
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $a->title }}</div>
                            <p class="line-clamp-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $excerpt($a->body, 140) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Join with an invite code --}}
    <flux:modal name="join" class="w-full max-w-md">
        <form wire:submit="joinWithCode" class="space-y-4" id="dolacz">
            <div>
                <flux:heading size="lg">{{ __('Dołącz z zaproszenia') }}</flux:heading>
                <flux:text>{{ __('Wklej kod z plakatu lub e-maila. Możesz też zeskanować kod QR aparatem telefonu.') }}</flux:text>
            </div>
            <flux:input wire:model="code" :label="__('Kod zaproszenia')" :placeholder="__('np. 3fA9…')" autocomplete="off" icon="key" />
            <flux:error name="code" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Anuluj') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Dołącz') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- School preview drawer --}}
    <flux:modal name="school-preview" variant="flyout" class="w-full max-w-md">
        @php($p = $this->previewSchool)
        <div wire:loading.flex wire:target="preview" class="flex-col gap-3">
            <flux:skeleton class="h-10 w-2/3" animate="shimmer" />
            <flux:skeleton class="h-24 w-full" animate="shimmer" />
        </div>
        @if ($p)
            <div class="space-y-6" wire:loading.remove wire:target="preview">
                <div class="flex items-center gap-3 pe-8">
                    <x-app.school-badge :school="$p" />
                    <div class="min-w-0">
                        <flux:heading size="lg" class="truncate">{{ $p->name }}</flux:heading>
                        <flux:text size="sm">{{ collect([$p->city, $p->short_name])->filter()->join(' · ') }}</flux:text>
                    </div>
                </div>

                @if ($p->tagline || $p->description)
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $p->tagline ?: \Illuminate\Support\Str::limit(strip_tags((string) $p->description), 220) }}</p>
                @endif

                @if ($p->address || $p->phone || $p->public_email || $p->website)
                    <dl class="space-y-2 text-sm">
                        @foreach (array_filter(['map-pin' => $p->address, 'phone' => $p->phone, 'envelope' => $p->public_email, 'globe-alt' => $p->website]) as $icon => $value)
                            <div class="flex items-start gap-2 text-zinc-700 dark:text-zinc-300">
                                <flux:icon :name="$icon" variant="micro" class="mt-0.5 shrink-0 text-zinc-400" />
                                <dd class="min-w-0 break-words">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($p->offers->isNotEmpty())
                    <div class="space-y-2">
                        <flux:heading>{{ __('Oferta') }}</flux:heading>
                        <ul class="divide-y divide-zinc-100 rounded-mci border border-zinc-200 dark:divide-zinc-700/60 dark:border-zinc-700">
                            @foreach ($p->offers as $offer)
                                <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $offer->name }}</span>
                                    <span class="shrink-0 text-xs text-zinc-500">{{ $offer->kind->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-2">
                    <flux:heading>{{ __('Najbliższe wydarzenia') }}</flux:heading>
                    @forelse ($p->events as $e)
                        <a href="{{ route('public.event', $e) }}" class="flex items-center gap-3 rounded-mci p-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <x-public.date-badge :date="$e->starts_at" size="sm" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $e->title }}</span>
                                <span class="flex items-center gap-1 text-xs text-zinc-500"><flux:icon :name="$e->category->icon()" variant="micro" /> {{ $e->category->name }}</span>
                            </span>
                        </a>
                    @empty
                        <flux:text size="sm">{{ __('Brak zaplanowanych wydarzeń publicznych.') }}</flux:text>
                    @endforelse
                </div>

                <div class="flex flex-col gap-2 border-t border-zinc-100 pt-4 sm:flex-row dark:border-zinc-700/60">
                    <flux:button variant="primary" :href="route('public.school', $p->slug)" icon="arrow-top-right-on-square" class="flex-1">{{ __('Pełna strona szkoły') }}</flux:button>
                    @if ($icsEnabled($p))
                        <flux:button :href="route('public.school.ics', $p->slug)" icon="calendar-days" class="flex-1">{{ __('Kalendarz (iCal)') }}</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
