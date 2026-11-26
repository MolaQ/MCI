<div>
    <section class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mci-wrap space-y-4 py-10">
            <a href="{{ \App\Livewire\Publicly\Home::url('szkoly') }}" class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"><flux:icon name="arrow-left" variant="micro" /> {{ __('Wszystkie szkoły') }}</a>
            <div class="mci-fade-up flex flex-wrap items-center gap-4">
                <x-app.school-badge :school="$school" class="!size-14 !text-sm" />
                <div>
                    <h1 class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $school->name }}</h1>
                    @if ($school->tagline)
                        <p class="text-zinc-600 dark:text-zinc-300">{{ $school->tagline }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="mci-wrap grid gap-8 py-10 lg:grid-cols-[minmax(0,1fr)_20rem] 2xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="min-w-0 space-y-10">
            {{-- Public announcements first --}}
            <section class="space-y-3" aria-labelledby="school-news">
                <h2 id="school-news" class="flex items-center gap-2 text-xl font-semibold text-zinc-900 dark:text-white"><flux:icon name="megaphone" variant="mini" class="text-zinc-500" /> {{ __('Komunikaty') }}</h2>
                @if ($announcements->isNotEmpty())
                    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($announcements as $a)
                            <a href="{{ route('public.announcement', $a) }}" wire:key="a-{{ $a->id }}" class="mci-fade-up group flex h-full flex-col gap-1.5 rounded-mci border border-zinc-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/70">
                                <div class="text-xs text-zinc-500">{{ $a->published_at->translatedFormat('j F Y') }}</div>
                                <div class="line-clamp-2 font-semibold text-zinc-900 group-hover:text-primary dark:text-white">{{ $a->title }}</div>
                                <p class="line-clamp-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $excerpt($a->body) }}</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('Brak komunikatów publicznych.') }}</flux:text>
                @endif
            </section>

            <section class="space-y-3" aria-labelledby="school-events">
                <h2 id="school-events" class="flex items-center gap-2 text-xl font-semibold text-zinc-900 dark:text-white"><flux:icon name="calendar-days" variant="mini" class="text-zinc-500" /> {{ __('Nadchodzące wydarzenia') }}</h2>
                <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    @forelse ($events as $event)
                        <x-event-card :event="$event" :href="route('public.event', $event)" :show-school="false" public wire:key="e-{{ $event->id }}" />
                    @empty
                        <flux:text>{{ __('Brak zaplanowanych wydarzeń publicznych.') }}</flux:text>
                    @endforelse
                </div>
            </section>

            @if ($school->description)
                <section class="space-y-2">
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('O szkole') }}</h2>
                    <div class="max-w-4xl whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $school->description }}</div>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('Kontakt') }}</flux:heading>
                @foreach ([['map-pin', $school->address ?: $school->city, null], ['phone', $school->phone, $school->phone ? 'tel:'.preg_replace('/\s+/', '', $school->phone) : null], ['envelope', $school->public_email, $school->public_email ? 'mailto:'.$school->public_email : null], ['globe-alt', $school->website ? parse_url($school->website, PHP_URL_HOST) : null, $school->website]] as [$icon, $text, $link])
                    @if ($text)
                        <div class="flex items-start gap-2 text-sm">
                            <flux:icon :name="$icon" variant="mini" class="mt-0.5 shrink-0 text-zinc-400" />
                            @if ($link)
                                <a href="{{ $link }}" class="text-primary hover:underline" @if ($icon === 'globe-alt') target="_blank" rel="noopener" @endif>{{ $text }}</a>
                            @else
                                <span>{{ $text }}</span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </flux:card>

            {{-- Educational offer: a light, optional block (feature "public.offers") --}}
            @if ($offers->isNotEmpty())
                <section class="space-y-3 rounded-mci border border-zinc-200 bg-zinc-100/60 p-4 dark:border-zinc-700 dark:bg-zinc-800/40" data-offer-block aria-labelledby="school-offer">
                    <h2 id="school-offer" class="flex items-center gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-200"><flux:icon name="academic-cap" variant="mini" class="text-zinc-500" /> {{ __('Oferta edukacyjna') }}</h2>
                    @foreach ($offers->groupBy(fn ($o) => $o->kind->value) as $group)
                        <div class="space-y-1.5">
                            <div class="text-xs text-zinc-500">{{ $group->first()->kind->label() }}</div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($group as $offer)
                                    <span wire:key="o-{{ $offer->id }}" class="rounded-full border border-zinc-200 bg-white px-2.5 py-0.5 text-sm text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200" @if ($offer->description) title="{{ $offer->description }}" @endif>{{ $offer->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

            @feature('public.tv')
                <flux:button :href="route('tv', ['szkola' => $school->slug])" icon="tv" class="w-full">{{ __('Tablica tej szkoły (TV)') }}</flux:button>
            @endfeature
        </aside>
    </div>
</div>
