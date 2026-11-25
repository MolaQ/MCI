@props(['event', 'featured' => false])

{{-- Public event card for the information centre grid. Public fields only. --}}
<a href="{{ route('public.event', $event) }}" {{ $attributes->class([
    'mci-fade-up group flex h-full flex-col overflow-hidden rounded-mci border border-zinc-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:border-zinc-700 dark:bg-zinc-800/70 dark:hover:border-zinc-600',
]) }}>
    @if ($featured && $event->cover)
        <div class="relative aspect-[21/9] w-full overflow-hidden bg-zinc-100 dark:bg-zinc-800">
            <img src="{{ $event->cover->url() }}" alt="" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-[1.02]" />
        </div>
    @endif
    <div @class(['flex flex-1 gap-4', 'p-6' => $featured, 'p-4' => ! $featured])>
        <x-public.date-badge :date="$event->starts_at" :size="$featured ? 'lg' : 'md'" />
        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1 font-medium text-zinc-700 dark:text-zinc-300"><flux:icon :name="$event->category->icon()" variant="micro" /> {{ $event->category->label() }}</span>
                @if ($event->school)
                    <span class="inline-flex items-center gap-1"><flux:icon name="building-library" variant="micro" /> {{ $event->school->short_name }}</span>
                @endif
            </div>
            <h3 @class(['font-semibold text-zinc-900 group-hover:text-primary dark:text-white', 'text-xl sm:text-2xl leading-tight' => $featured, 'line-clamp-2' => ! $featured])>{{ $event->title }}</h3>
            <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-sm text-zinc-600 dark:text-zinc-300">
                <span class="inline-flex items-center gap-1"><flux:icon name="clock" variant="micro" class="text-zinc-400" /> {{ $event->all_day ? __('Cały dzień') : $event->starts_at->format('H:i') }}</span>
                @if ($event->location)
                    <span class="inline-flex min-w-0 items-center gap-1"><flux:icon name="map-pin" variant="micro" class="shrink-0 text-zinc-400" /> <span class="truncate">{{ $event->location }}</span></span>
                @endif
            </div>
            @if ($event->public_description)
                <p @class(['text-sm text-zinc-500 dark:text-zinc-400', 'line-clamp-3 mt-1' => $featured, 'line-clamp-2' => ! $featured])>{{ $event->public_description }}</p>
            @endif
            @if ($featured)
                <span class="mt-auto inline-flex items-center gap-1 pt-3 text-sm font-medium text-primary">{{ __('Szczegóły wydarzenia') }} <flux:icon name="arrow-right" variant="micro" class="transition group-hover:translate-x-0.5" /></span>
            @endif
        </div>
    </div>
</a>
