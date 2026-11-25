<div x-data="mciFullscreen" x-on:keydown.escape.window="if (! active) window.location = @js(route('home'))"
     x-on:keydown.f.window="toggle()" class="flex h-full flex-col gap-6 p-6 lg:p-10" wire:poll.{{ $pollSeconds }}s>
    <header class="flex items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            @if ($currentSchool)
                <x-app.school-badge :school="$currentSchool" class="!size-14 !text-sm" />
            @else
                <span class="flex size-14 items-center justify-center rounded-mci bg-primary text-lg font-bold text-primary-foreground">{{ $appearance->shortName() }}</span>
            @endif
            <div>
                <div class="text-3xl font-bold lg:text-4xl">{{ $currentSchool?->name ?? $appearance->schoolName() }}</div>
                <div class="text-lg text-zinc-400">{{ __('Wydarzenia i komunikaty') }}</div>
            </div>
        </div>
        <div class="flex items-center gap-6" x-data="mciClock">
            <div class="text-end">
                <div class="text-5xl font-semibold tabular-nums lg:text-6xl" x-text="time"></div>
                <div class="text-lg text-zinc-400 first-letter:uppercase" x-text="date"></div>
            </div>
            <div class="flex flex-col gap-2">
                <button type="button" x-on:click="toggle()" class="flex size-11 items-center justify-center rounded-mci bg-white/10 text-white transition hover:bg-white/20" :aria-label="active ? @js(__('Wyjdź z pełnego ekranu')) : @js(__('Pełny ekran'))">
                    <flux:icon x-show="! active" name="arrows-pointing-out" />
                    <flux:icon x-show="active" x-cloak name="arrows-pointing-in" />
                </button>
                <a href="{{ route('home') }}" class="flex size-11 items-center justify-center rounded-mci bg-white/10 text-white transition hover:bg-white/20" aria-label="{{ __('Zamknij tryb TV') }}" title="{{ __('Zamknij (Esc)') }}">
                    <flux:icon name="x-mark" />
                </a>
            </div>
        </div>
    </header>

    <div class="grid min-h-0 flex-1 gap-6 lg:grid-cols-3">
        <section class="flex min-h-0 flex-col gap-4 lg:col-span-2">
            <h2 class="text-2xl font-semibold text-zinc-300">{{ __('Dziś') }}</h2>
            @forelse ($today as $event)
                <div wire:key="t-{{ $event->id }}" class="mci-fade-up flex items-center gap-6 rounded-2xl bg-primary/90 p-6 text-primary-foreground">
                    <div class="text-4xl font-bold tabular-nums">{{ $event->all_day ? __('cały dzień') : $event->starts_at->format('H:i') }}</div>
                    <div class="min-w-0">
                        <div class="truncate text-3xl font-semibold">{{ $event->title }}</div>
                        <div class="text-xl opacity-80">{{ collect([$event->location, $currentSchool ? null : $event->school->short_name])->filter()->implode(' · ') }}</div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-white/5 p-6 text-2xl text-zinc-400">{{ __('Dziś brak wydarzeń publicznych.') }}</div>
            @endforelse

            <h2 class="mt-2 text-2xl font-semibold text-zinc-300">{{ __('W najbliższych dniach') }}</h2>
            <div class="grid min-h-0 gap-3 overflow-hidden md:grid-cols-2">
                @foreach ($upcoming as $event)
                    <div wire:key="u-{{ $event->id }}" class="mci-fade-up flex items-center gap-4 rounded-2xl bg-white/5 p-4">
                        <div class="flex w-16 shrink-0 flex-col items-center rounded-xl bg-white/10 py-2">
                            <span class="text-sm uppercase text-zinc-400">{{ $event->starts_at->translatedFormat('M') }}</span>
                            <span class="text-3xl font-bold tabular-nums">{{ $event->starts_at->format('j') }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-xl font-semibold">{{ $event->title }}</div>
                            <div class="truncate text-zinc-400">{{ collect([$event->all_day ? null : $event->starts_at->format('H:i'), $event->location, $currentSchool ? null : $event->school->short_name])->filter()->implode(' · ') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="flex min-h-0 flex-col gap-4">
            <h2 class="text-2xl font-semibold text-zinc-300">{{ __('Komunikaty') }}</h2>
            @forelse ($announcements as $a)
                <div wire:key="a-{{ $a->id }}" @class(['mci-fade-up space-y-2 rounded-2xl p-5', 'bg-amber-500/20 ring-1 ring-amber-400/40' => $a->priority !== \App\Enums\AnnouncementPriority::Normal, 'bg-white/5' => $a->priority === \App\Enums\AnnouncementPriority::Normal])>
                    <div class="text-sm text-zinc-400">{{ $a->school->short_name }} · {{ $a->published_at->translatedFormat('j F') }}</div>
                    <div class="text-2xl font-semibold">{{ $a->title }}</div>
                    <p class="line-clamp-4 text-lg text-zinc-300">{{ $excerpt($a->body) }}</p>
                </div>
            @empty
                <div class="rounded-2xl bg-white/5 p-5 text-xl text-zinc-400">{{ __('Brak komunikatów.') }}</div>
            @endforelse
        </section>
    </div>

    <footer class="flex justify-between text-sm text-zinc-500">
        <span>{{ __('Odświeżanie automatyczne') }}</span>
        <span>{{ __('F — pełny ekran · Esc — wyjście') }}</span>
    </footer>
</div>
