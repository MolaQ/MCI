<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="pointer-events-none absolute -end-24 -top-24 size-96 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-6xl gap-8 px-4 py-12 md:grid-cols-5 md:py-16">
            <div class="mci-fade-up space-y-5 md:col-span-3">
                <flux:badge icon="building-library" color="zinc">{{ trans_choice(':count szkoła w portalu|:count szkoły w portalu|:count szkół w portalu', $this->schools->count()) }}</flux:badge>
                <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ $appearance->schoolName() }}</h1>
                <p class="max-w-xl text-lg text-zinc-600 dark:text-zinc-300">{{ __('Oferta szkół, dni otwarte, konkursy i komunikaty — wszystko w jednym miejscu, bez logowania.') }}</p>
                <div class="flex flex-wrap gap-2">
                    <flux:button href="#wydarzenia" variant="primary" icon="calendar-days">{{ __('Najbliższe wydarzenia') }}</flux:button>
                    <flux:button href="#szkoly" icon="academic-cap">{{ __('Oferta szkół') }}</flux:button>
                </div>
            </div>
            <div class="mci-fade-up md:col-span-2" style="animation-delay: 80ms">
                <flux:card class="space-y-4">
                    @auth
                        <flux:heading size="lg">{{ __('Jesteś zalogowany(-a)') }}</flux:heading>
                        <flux:text>{{ __('Komunikaty, grupy i wydarzenia wewnętrzne znajdziesz na swoim pulpicie.') }}</flux:text>
                        <flux:button :href="route('dashboard')" variant="primary" class="w-full" icon="squares-2x2">{{ __('Przejdź do pulpitu') }}</flux:button>
                    @else
                        <flux:heading size="lg">{{ __('Dla pracowników szkół') }}</flux:heading>
                        <flux:button :href="route('login')" variant="primary" class="w-full" icon="arrow-right-end-on-rectangle">{{ __('Zaloguj się') }}</flux:button>
                        <form wire:submit="joinWithCode" class="space-y-2" id="dolacz">
                            <flux:label>{{ __('Masz kod zaproszenia?') }}</flux:label>
                            <div class="flex gap-2">
                                <flux:input wire:model="code" :placeholder="__('Wklej kod z plakatu lub e-maila')" autocomplete="off" class="flex-1" />
                                <flux:button type="submit" icon="arrow-right" :aria-label="__('Dołącz')" />
                            </div>
                            <flux:error name="code" />
                            <flux:text size="sm" class="flex items-center gap-1"><flux:icon name="qr-code" variant="micro" /> {{ __('Albo zeskanuj kod QR z plakatu telefonem.') }}</flux:text>
                        </form>
                    @endauth
                </flux:card>
            </div>
        </div>
    </section>

    {{-- Events --}}
    <section id="wydarzenia" class="mx-auto max-w-6xl scroll-mt-20 space-y-5 px-4 py-10">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Wydarzenia publiczne') }}</h2>
                <p class="text-zinc-500">{{ __('Dni otwarte, konkursy, zawody międzyszkolne i uroczystości.') }}</p>
            </div>
            <flux:button :href="route('tv', array_filter(['szkola' => $school]))" size="sm" variant="ghost" icon="tv">{{ __('Pokaż na ekranie') }}</flux:button>
        </div>

        <div class="space-y-2">
            <div class="flex flex-wrap gap-2">
                <x-ui.pill wire:click="$set('school', '')" :active="$school === ''">{{ __('Wszystkie szkoły') }}</x-ui.pill>
                @foreach ($this->schools as $s)
                    <x-ui.pill wire:click="$set('school', '{{ $s->slug }}')" :active="$school === $s->slug" :count="$s->upcoming_count">{{ $s->short_name }}</x-ui.pill>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach (['upcoming' => __('Nadchodzące'), 'week' => __('Ten tydzień'), 'month' => __('Ten miesiąc')] as $key => $label)
                    <x-ui.pill wire:click="$set('when', '{{ $key }}')" :active="$when === $key">{{ $label }}</x-ui.pill>
                @endforeach
                <span class="mx-1 hidden w-px bg-zinc-200 sm:block dark:bg-zinc-700"></span>
                <x-ui.pill wire:click="$set('category', '')" :active="$category === ''">{{ __('Każdy rodzaj') }}</x-ui.pill>
                @foreach ($categories as $c)
                    <x-ui.pill wire:click="$set('category', '{{ $c->value }}')" :active="$category === $c->value"><flux:icon :name="$c->icon()" variant="micro" /> {{ $c->label() }}</x-ui.pill>
                @endforeach
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-60">
            @forelse ($this->events as $event)
                <x-event-card :event="$event" :href="route('public.event', $event)" public wire:key="e-{{ $event->id }}" />
            @empty
                <div class="rounded-mci border border-dashed border-zinc-300 bg-white p-8 text-center md:col-span-2 lg:col-span-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <x-ui.empty-state icon="calendar" :title="__('Brak wydarzeń dla tych filtrów')" :description="__('Spróbuj innej szkoły lub terminu.')" class="!py-2" />
                </div>
            @endforelse
        </div>
    </section>

    {{-- Schools --}}
    <section id="szkoly" class="scroll-mt-20 border-y border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-6xl space-y-5 px-4 py-10">
            <div>
                <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Szkoły i oferta') }}</h2>
                <p class="text-zinc-500">{{ __('Poznaj kierunki kształcenia i skontaktuj się z sekretariatem.') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->schools as $s)
                    <a href="{{ route('public.school', $s->slug) }}" wire:key="s-{{ $s->id }}"
                       class="mci-fade-up group flex flex-col gap-3 rounded-mci border border-zinc-200 p-5 transition-all duration-150 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md dark:border-zinc-700">
                        <div class="flex items-center gap-3">
                            <x-app.school-badge :school="$s" />
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-zinc-900 group-hover:text-primary dark:text-white">{{ $s->name }}</div>
                                @if ($s->city)
                                    <div class="text-sm text-zinc-500">{{ $s->city }}</div>
                                @endif
                            </div>
                        </div>
                        @if ($s->tagline)
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $s->tagline }}</p>
                        @endif
                        <div class="mt-auto flex gap-4 text-sm text-zinc-500">
                            @if (app(\App\Support\Features::class)->enabled(\App\Enums\Feature::PublicOffers, $s))
                                <span class="inline-flex items-center gap-1"><flux:icon name="academic-cap" variant="micro" /> {{ trans_choice(':count kierunek|:count kierunki|:count kierunków', $s->offers_count) }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1"><flux:icon name="calendar" variant="micro" /> {{ trans_choice(':count wydarzenie|:count wydarzenia|:count wydarzeń', $s->upcoming_count) }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Public announcements --}}
    <section id="komunikaty" class="mx-auto max-w-6xl scroll-mt-20 space-y-5 px-4 py-10">
        <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Komunikaty publiczne') }}</h2>
        <div class="grid gap-3 md:grid-cols-2">
            @forelse ($this->announcements as $a)
                <a href="{{ route('public.announcement', $a) }}" wire:key="a-{{ $a->id }}"
                   class="mci-fade-up flex flex-col gap-2 rounded-mci border border-zinc-200 bg-white p-4 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/70 dark:hover:bg-zinc-800">
                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                        <x-app.school-badge :school="$a->school" size="sm" /> {{ $a->school->short_name }} · {{ $a->published_at->translatedFormat('j F Y') }}
                        @if ($a->priority !== \App\Enums\AnnouncementPriority::Normal)
                            <flux:badge size="sm" :color="$a->priority->color()">{{ $a->priority->label() }}</flux:badge>
                        @endif
                    </div>
                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $a->title }}</div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $excerpt($a->body) }}</p>
                </a>
            @empty
                <flux:text>{{ __('Brak komunikatów publicznych.') }}</flux:text>
            @endforelse
        </div>
    </section>
</div>
