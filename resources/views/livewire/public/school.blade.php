<div>
    <section class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-6xl space-y-4 px-4 py-10">
            <a href="{{ route('home') }}#szkoly" class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"><flux:icon name="arrow-left" variant="micro" /> {{ __('Wszystkie szkoły') }}</a>
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

    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-3">
        <div class="space-y-8 lg:col-span-2">
            @if ($school->description)
                <section class="space-y-2">
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('O szkole') }}</h2>
                    <div class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $school->description }}</div>
                </section>
            @endif

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('Oferta edukacyjna') }}</h2>
                @if ($kinds->count() > 1)
                    <div class="flex flex-wrap gap-2">
                        <x-ui.pill wire:click="$set('kind', '')" :active="$kind === ''">{{ __('Wszystko') }}</x-ui.pill>
                        @foreach ($kinds as $k)
                            <x-ui.pill wire:click="$set('kind', '{{ $k->value }}')" :active="$kind === $k->value">{{ $k->label() }}</x-ui.pill>
                        @endforeach
                    </div>
                @endif
                <div class="grid gap-3 sm:grid-cols-2">
                    @forelse ($offers as $offer)
                        <div wire:key="o-{{ $offer->id }}" class="mci-fade-up rounded-mci border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/70">
                            <div class="text-xs font-medium uppercase tracking-wide text-primary">{{ $offer->kind->label() }}</div>
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $offer->name }}</div>
                            @if ($offer->description)
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $offer->description }}</p>
                            @endif
                        </div>
                    @empty
                        <flux:text>{{ __('Szkoła nie opublikowała jeszcze oferty.') }}</flux:text>
                    @endforelse
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('Nadchodzące wydarzenia') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @forelse ($events as $event)
                        <x-event-card :event="$event" :href="route('public.event', $event)" :show-school="false" public wire:key="e-{{ $event->id }}" />
                    @empty
                        <flux:text>{{ __('Brak zaplanowanych wydarzeń publicznych.') }}</flux:text>
                    @endforelse
                </div>
            </section>
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

            @if ($announcements->isNotEmpty())
                <flux:card class="space-y-3">
                    <flux:heading size="lg">{{ __('Komunikaty') }}</flux:heading>
                    @foreach ($announcements as $a)
                        <a href="{{ route('public.announcement', $a) }}" class="block space-y-0.5 rounded-mci p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <div class="text-xs text-zinc-500">{{ $a->published_at->translatedFormat('j F Y') }}</div>
                            <div class="font-medium">{{ $a->title }}</div>
                        </a>
                    @endforeach
                </flux:card>
            @endif

            <flux:button :href="route('tv', ['szkola' => $school->slug])" icon="tv" class="w-full">{{ __('Tablica tej szkoły (TV)') }}</flux:button>
        </aside>
    </div>
</div>
