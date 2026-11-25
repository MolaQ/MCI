<div class="pb-16">
    @php($wrap = 'mx-auto w-full max-w-6xl px-4 2xl:max-w-7xl')

    {{-- 1. Identity, live summary and quick actions --}}
    <section class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="{{ $wrap }} grid gap-8 py-10 md:py-14 lg:grid-cols-[1fr_20rem] lg:items-end">
            <div class="mci-fade-up space-y-5">
                <p class="inline-flex items-center gap-2 text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500/60 motion-reduce:animate-none"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span></span>
                    {{ __('Centrum informacyjne szkół') }}
                </p>
                <h1 class="max-w-3xl text-3xl font-bold tracking-tight text-balance text-zinc-900 sm:text-4xl lg:text-5xl dark:text-white">{{ $appearance->schoolName() }}</h1>
                <p class="max-w-2xl text-lg text-zinc-600 dark:text-zinc-300">{{ __('Dni otwarte, konkursy, oferta szkół i komunikaty w jednym miejscu, bez logowania.') }}</p>

                <dl class="grid max-w-2xl grid-cols-2 gap-px overflow-hidden rounded-mci border border-zinc-200 bg-zinc-200 sm:grid-cols-4 dark:border-zinc-700 dark:bg-zinc-700" aria-label="{{ __('Podsumowanie') }}">
                    @foreach ([
                        ['building-library', __('Szkoły'), $this->summary['schools']],
                        ['sun', __('Dziś'), $this->summary['today']],
                        ['calendar-days', __('Najbliższe 7 dni'), $this->summary['week']],
                        ['megaphone', __('Komunikaty'), $this->summary['announcements']],
                    ] as [$icon, $label, $value])
                        <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                            <dt class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400"><flux:icon :name="$icon" variant="micro" /> {{ $label }}</dt>
                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <nav class="mci-fade-up grid grid-cols-3 gap-2 lg:grid-cols-1" style="animation-delay: 80ms" aria-label="{{ __('Szybkie akcje') }}">
                @php($tile = 'group flex flex-col items-center gap-2 rounded-mci border border-zinc-200 bg-white p-3 text-center sm:flex-row sm:gap-3 sm:text-start transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:border-zinc-700 dark:bg-zinc-800/70')
                @php($iconBox = 'flex size-10 shrink-0 items-center justify-center rounded-mci')
                @auth
                    <a href="{{ route('dashboard') }}" class="{{ $tile }}">
                        <span class="{{ $iconBox }} bg-primary text-primary-foreground"><flux:icon name="squares-2x2" variant="mini" /></span>
                        <span class="min-w-0"><span class="block text-sm font-semibold text-zinc-900 sm:text-base dark:text-white">{{ __('Mój pulpit') }}</span><span class="hidden text-xs text-zinc-500 sm:block">{{ __('Komunikaty i grupy wewnętrzne') }}</span></span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="{{ $tile }}">
                        <span class="{{ $iconBox }} bg-primary text-primary-foreground"><flux:icon name="arrow-right-end-on-rectangle" variant="mini" /></span>
                        <span class="min-w-0"><span class="block text-sm font-semibold text-zinc-900 sm:text-base dark:text-white">{{ __('Zaloguj się') }}</span><span class="hidden text-xs text-zinc-500 sm:block">{{ __('Dla pracowników szkół') }}</span></span>
                    </a>
                    @if ($invites)
                        <flux:modal.trigger name="join">
                            <button type="button" class="{{ $tile }}">
                                <span class="{{ $iconBox }} border border-zinc-200 text-zinc-700 dark:border-zinc-600 dark:text-zinc-200"><flux:icon name="qr-code" variant="mini" /></span>
                                <span class="min-w-0"><span class="block text-sm font-semibold text-zinc-900 sm:text-base dark:text-white">{{ __('Dołącz') }}</span><span class="hidden text-xs text-zinc-500 sm:block">{{ __('Kod zaproszenia lub QR') }}</span></span>
                            </button>
                        </flux:modal.trigger>
                    @endif
                @endauth
                @if ($tv)
                    <a href="{{ route('tv', array_filter(['szkola' => $school])) }}" class="{{ $tile }}">
                        <span class="{{ $iconBox }} border border-zinc-200 text-zinc-700 dark:border-zinc-600 dark:text-zinc-200"><flux:icon name="tv" variant="mini" /></span>
                        <span class="min-w-0"><span class="block text-sm font-semibold text-zinc-900 sm:text-base dark:text-white">{{ __('Tryb TV') }}</span><span class="hidden text-xs text-zinc-500 sm:block">{{ __('Tablica na pełnym ekranie') }}</span></span>
                    </a>
                @endif
            </nav>
        </div>
    </section>

    {{-- 2. One row of filters --}}
    <section id="wydarzenia" class="{{ $wrap }} scroll-mt-20 pt-8">
        <div class="flex items-center gap-2 overflow-x-auto pb-2 [scrollbar-width:none]" role="toolbar" aria-label="{{ __('Filtry') }}">
            @php($pill = 'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors')
            @php($pillOn = 'border-primary bg-primary text-primary-foreground')
            @php($pillOff = 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700')

            <flux:dropdown position="bottom" align="start">
                <button type="button" data-filter="school" class="{{ $pill }} {{ $school !== '' ? $pillOn : $pillOff }}">
                    <flux:icon name="building-library" variant="micro" /> {{ $schoolLabel ?? __('Wszystkie szkoły') }} <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                </button>
                <flux:menu class="max-h-80 overflow-y-auto">
                    <flux:menu.item wire:click="setFilter('school', '')" :icon="$school === '' ? 'check' : 'squares-2x2'">{{ __('Wszystkie szkoły') }}</flux:menu.item>
                    <flux:menu.separator />
                    @foreach ($this->schools as $s)
                        <flux:menu.item wire:click="setFilter('school', '{{ $s->slug }}')" :icon="$school === $s->slug ? 'check' : 'building-library'">
                            <span class="flex w-full items-center justify-between gap-4">{{ $s->short_name }} <span class="text-xs tabular-nums text-zinc-400">{{ $s->upcoming_count }}</span></span>
                        </flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>

            <flux:dropdown position="bottom" align="start">
                <button type="button" data-filter="category" class="{{ $pill }} {{ $category !== '' ? $pillOn : $pillOff }}">
                    <flux:icon name="tag" variant="micro" /> {{ $categoryLabel ?? __('Każdy rodzaj') }} <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                </button>
                <flux:menu class="max-h-80 overflow-y-auto">
                    <flux:menu.item wire:click="setFilter('category', '')" :icon="$category === '' ? 'check' : 'squares-2x2'">{{ __('Każdy rodzaj') }}</flux:menu.item>
                    <flux:menu.separator />
                    @forelse ($this->categories as ['term' => $term, 'count' => $count])
                        <flux:menu.item wire:click="setFilter('category', '{{ $term->key }}')" :icon="$category === $term->key ? 'check' : $term->icon()">
                            <span @class(['flex w-full items-center justify-between gap-4', 'font-semibold' => $category === $term->key])>{{ $term->label() }} <span class="text-xs tabular-nums text-zinc-400">{{ $count }}</span></span>
                        </flux:menu.item>
                    @empty
                        <flux:menu.item disabled>{{ __('Brak wydarzeń w tym zakresie') }}</flux:menu.item>
                    @endforelse
                </flux:menu>
            </flux:dropdown>

            <flux:dropdown position="bottom" align="start">
                <button type="button" data-filter="when" class="{{ $pill }} {{ $when !== 'upcoming' ? $pillOn : $pillOff }}">
                    <flux:icon name="calendar" variant="micro" /> {{ $this->timeframes()[$when] }} <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                </button>
                <flux:menu>
                    @foreach ($this->timeframes() as $key => $label)
                        <flux:menu.item wire:click="setFilter('when', '{{ $key }}')" :icon="$when === $key ? 'check' : 'calendar'">{{ $label }}</flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>

            @if ($filtered)
                <button type="button" wire:click="resetFilters" class="{{ $pill }} border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
                    <flux:icon name="x-mark" variant="micro" /> {{ __('Wyczyść') }}
                </button>
            @endif

            <span class="ms-auto hidden shrink-0 items-center gap-1.5 text-sm text-zinc-500 sm:inline-flex" wire:loading.class="opacity-50">
                <flux:icon.loading variant="micro" wire:loading />
                {{ trans_choice('{0} brak wydarzeń|{1} :count wydarzenie|[2,4] :count wydarzenia|[5,*] :count wydarzeń', $list->count() + ($hasMore ? 1 : 0), ['count' => $hasMore ? $list->count().'+' : $list->count()]) }}
            </span>
        </div>
    </section>

    {{-- 3. Featured grid: next event + announcements, then the rest --}}
    <section class="{{ $wrap }} mt-4 space-y-4" wire:loading.class="opacity-60" wire:target="setFilter,resetFilters">
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($list->isNotEmpty())
                    <x-public.event-tile :event="$list->first()" featured wire:key="f-{{ $list->first()->id }}" />
                @else
                    <div class="flex h-full min-h-56 flex-col items-center justify-center gap-2 rounded-mci border border-dashed border-zinc-300 bg-white p-8 text-center dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:icon name="calendar" class="size-8 text-zinc-400" />
                        <p class="font-medium text-zinc-900 dark:text-white">{{ __('Brak wydarzeń dla tych filtrów') }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Spróbuj innej szkoły, rodzaju lub terminu.') }}</p>
                        @if ($filtered)
                            <flux:button size="sm" class="mt-2" wire:click="resetFilters">{{ __('Pokaż wszystko') }}</flux:button>
                        @endif
                    </div>
                @endif
            </div>

            <aside id="komunikaty" class="scroll-mt-20 flex flex-col rounded-mci border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/70" aria-labelledby="komunikaty-h">
                <div class="flex items-center justify-between gap-2 border-b border-zinc-100 px-5 py-4 dark:border-zinc-700/60">
                    <h2 id="komunikaty-h" class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-white"><flux:icon name="megaphone" variant="mini" class="text-zinc-500" /> {{ __('Komunikaty') }}</h2>
                </div>
                <div class="flex-1 divide-y divide-zinc-100 dark:divide-zinc-700/60">
                    @forelse ($this->announcements as $a)
                        <a href="{{ route('public.announcement', $a) }}" wire:key="a-{{ $a->id }}" class="group block px-5 py-3.5 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <div class="flex items-center gap-2 text-xs text-zinc-500">
                                @if ($a->isPinned())
                                    <flux:icon name="bookmark" variant="micro" class="text-primary" />
                                @endif
                                <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $a->school->short_name }}</span>
                                <span>· {{ $a->published_at->translatedFormat('j M') }}</span>
                                @if ($a->priority !== \App\Enums\AnnouncementPriority::Normal)
                                    <flux:badge size="sm" :color="$a->priority->color()" class="ms-auto">{{ $a->priority->label() }}</flux:badge>
                                @endif
                            </div>
                            <div class="mt-1 line-clamp-1 font-medium text-zinc-900 group-hover:text-primary dark:text-white">{{ $a->title }}</div>
                            <p class="line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $excerpt($a->body, 120) }}</p>
                        </a>
                    @empty
                        <div class="flex h-full flex-col items-center justify-center gap-1 px-5 py-10 text-center">
                            <flux:icon name="megaphone" class="size-6 text-zinc-300" />
                            <p class="text-sm text-zinc-500">{{ __('Brak komunikatów publicznych.') }}</p>
                        </div>
                    @endforelse
                </div>
            </aside>
        </div>

        @if ($list->count() > 1)
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($list->slice(1) as $event)
                    <x-public.event-tile :event="$event" wire:key="e-{{ $event->id }}" />
                @endforeach
            </div>
        @endif

        @if ($hasMore)
            <div class="flex justify-center pt-2">
                <flux:button wire:click="loadMore" icon="chevron-down">{{ __('Pokaż więcej wydarzeń') }}</flux:button>
            </div>
        @endif
    </section>

    {{-- 4. School directory --}}
    <section id="szkoly" class="{{ $wrap }} mt-16 scroll-mt-20 space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ __('Szkoły i oferta') }}</h2>
                <p class="text-zinc-500">{{ __('Kliknij szkołę, aby zobaczyć kierunki, najbliższe wydarzenia i kalendarz.') }}</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->schools as $s)
                <button type="button" wire:click="openSchool('{{ $s->slug }}')" wire:key="s-{{ $s->id }}" data-school="{{ $s->slug }}"
                    class="mci-fade-up group flex h-full flex-col gap-4 rounded-mci border border-zinc-200 bg-white p-5 text-start transition duration-200 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:border-zinc-700 dark:bg-zinc-800/70">
                    <div class="flex items-center gap-3">
                        <x-app.school-badge :school="$s" />
                        <div class="min-w-0">
                            <div class="line-clamp-2 font-semibold text-zinc-900 group-hover:text-primary dark:text-white">{{ $s->name }}</div>
                            @if ($s->city)
                                <div class="flex items-center gap-1 text-sm text-zinc-500"><flux:icon name="map-pin" variant="micro" /> {{ $s->city }}</div>
                            @endif
                        </div>
                    </div>
                    <p class="line-clamp-2 min-h-10 text-sm text-zinc-600 dark:text-zinc-300">{{ $s->tagline }}</p>
                    <div class="mt-auto flex items-center gap-4 border-t border-zinc-100 pt-3 text-sm text-zinc-500 dark:border-zinc-700/60">
                        @if (app(\App\Support\Features::class)->enabled(\App\Enums\Feature::PublicOffers, $s))
                            <span class="inline-flex items-center gap-1"><flux:icon name="academic-cap" variant="micro" /> {{ trans_choice(':count kierunek|:count kierunki|:count kierunków', $s->offers_count) }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1"><flux:icon name="calendar" variant="micro" /> {{ trans_choice(':count wydarzenie|:count wydarzenia|:count wydarzeń', $s->upcoming_count) }}</span>
                        <flux:icon name="arrow-right" variant="micro" class="ms-auto text-zinc-400 transition group-hover:translate-x-0.5 group-hover:text-primary" />
                    </div>
                </button>
            @endforeach
        </div>
    </section>

    {{-- School preview drawer --}}
    <flux:modal name="school-preview" variant="flyout" class="w-full max-w-md">
        @if ($p = $this->previewSchool)
            @php($ps = $p['school'])
            <div class="space-y-6" wire:key="preview-{{ $ps->id }}">
                <div class="flex items-center gap-3 pe-8">
                    <x-app.school-badge :school="$ps" />
                    <div class="min-w-0">
                        <flux:heading size="lg">{{ $ps->name }}</flux:heading>
                        @if ($ps->city)
                            <flux:text size="sm">{{ $ps->city }}</flux:text>
                        @endif
                    </div>
                </div>
                @if ($ps->tagline)
                    <flux:text>{{ $ps->tagline }}</flux:text>
                @endif

                @if ($ps->address || $ps->phone || $ps->public_email || $ps->website)
                    <dl class="space-y-1.5 text-sm text-zinc-600 dark:text-zinc-300">
                        @if ($ps->address)<div class="flex gap-2"><flux:icon name="map-pin" variant="micro" class="mt-0.5 shrink-0 text-zinc-400" /> {{ $ps->address }}</div>@endif
                        @if ($ps->phone)<div class="flex gap-2"><flux:icon name="phone" variant="micro" class="mt-0.5 shrink-0 text-zinc-400" /> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $ps->phone) }}" class="hover:underline">{{ $ps->phone }}</a></div>@endif
                        @if ($ps->public_email)<div class="flex gap-2"><flux:icon name="envelope" variant="micro" class="mt-0.5 shrink-0 text-zinc-400" /> <a href="mailto:{{ $ps->public_email }}" class="hover:underline">{{ $ps->public_email }}</a></div>@endif
                    </dl>
                @endif

                @if ($p['offers']->isNotEmpty())
                    <div class="space-y-2">
                        <flux:heading size="sm">{{ __('Kierunki i oferta') }}</flux:heading>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($p['offers'] as $o)
                                <span class="inline-flex items-center gap-1 rounded-full border border-zinc-200 px-2.5 py-0.5 text-sm text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ $o->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="space-y-2">
                    <flux:heading size="sm">{{ __('Najbliższe wydarzenia') }}</flux:heading>
                    @forelse ($p['events'] as $e)
                        <a href="{{ route('public.event', $e) }}" class="group flex items-center gap-3 rounded-mci p-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <x-public.date-badge :date="$e->starts_at" class="!w-12 !py-1" />
                            <div class="min-w-0">
                                <div class="truncate font-medium text-zinc-900 group-hover:text-primary dark:text-white">{{ $e->title }}</div>
                                <div class="flex items-center gap-1 text-xs text-zinc-500"><flux:icon :name="$e->category->icon()" variant="micro" /> {{ $e->category->label() }}</div>
                            </div>
                        </a>
                    @empty
                        <flux:text size="sm">{{ __('Brak zaplanowanych wydarzeń publicznych.') }}</flux:text>
                    @endforelse
                </div>

                <div class="grid gap-2">
                    <flux:button :href="route('public.school', $ps->slug)" variant="primary" icon="arrow-top-right-on-square">{{ __('Strona szkoły') }}</flux:button>
                    <div class="grid grid-cols-2 gap-2">
                        <flux:button wire:click="setFilter('school', '{{ $ps->slug }}')" x-on:click="$flux.modal('school-preview').close(); document.getElementById('wydarzenia').scrollIntoView({ behavior: 'smooth' })" icon="funnel">{{ __('Wydarzenia szkoły') }}</flux:button>
                        @if ($p['ics'])
                            <flux:button :href="route('public.school.ics', $ps->slug)" icon="calendar-days">{{ __('Kalendarz iCal') }}</flux:button>
                        @endif
                    </div>
                    @if ($ps->website)
                        <flux:button :href="$ps->website" target="_blank" rel="noopener" variant="ghost" icon="globe-alt">{{ __('Strona internetowa') }}</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Join with an invite code --}}
    @guest
        <flux:modal name="join" class="w-full max-w-md">
            <form wire:submit="joinWithCode" class="space-y-4">
                <flux:heading size="lg">{{ __('Dołącz do szkoły') }}</flux:heading>
                <flux:text>{{ __('Wklej kod z plakatu lub e-maila. Możesz też zeskanować kod QR z plakatu aparatem telefonu.') }}</flux:text>
                <flux:input wire:model="code" :placeholder="__('Kod zaproszenia')" autocomplete="off" />
                <flux:error name="code" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Anuluj') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Dalej') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endguest
</div>
