@props(['event', 'href' => null, 'showSchool' => true, 'public' => false])

{{-- One event, used on the public front, in the app and on the TV board. Public mode prints public fields only. --}}
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class('mci-fade-up group flex gap-4 rounded-mci border border-zinc-200 bg-white p-4 transition-all duration-150 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/70') }}>
    <div class="flex w-14 shrink-0 flex-col items-center justify-center rounded-mci bg-primary/10 py-2 text-primary">
        <span class="text-xs font-medium uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
        <span class="text-2xl font-bold leading-none tabular-nums">{{ $event->starts_at->format('j') }}</span>
        <span class="text-[0.65rem] text-primary/80">{{ $event->starts_at->translatedFormat('D') }}</span>
    </div>
    <div class="min-w-0 flex-1 space-y-1">
        <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
            <span class="inline-flex items-center gap-1"><flux:icon :name="$event->category->icon()" variant="micro" /> {{ $event->category->label() }}</span>
            @unless ($public)
                <span class="inline-flex items-center gap-1"><flux:icon :name="$event->visibility->icon()" variant="micro" /> {{ $event->visibility->label() }}</span>
            @endunless
            @if ($event->status === 'cancelled')
                <flux:badge size="sm" color="red">{{ __('Odwołane') }}</flux:badge>
            @endif
        </div>
        <div class="font-semibold text-zinc-900 group-hover:text-primary dark:text-white">{{ $event->title }}</div>
        <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-sm text-zinc-600 dark:text-zinc-300">
            <span class="inline-flex items-center gap-1"><flux:icon name="clock" variant="micro" class="text-zinc-400" /> {{ $event->all_day ? __('Cały dzień') : $event->starts_at->format('H:i') }}</span>
            @if ($event->location)
                <span class="inline-flex items-center gap-1"><flux:icon name="map-pin" variant="micro" class="text-zinc-400" /> {{ $event->location }}</span>
            @endif
            @if ($showSchool && $event->school)
                <span class="inline-flex items-center gap-1"><x-app.school-badge :school="$event->school" size="sm" class="!size-4 !text-[0.4rem]" /> {{ $event->school->short_name }}</span>
            @endif
        </div>
        @if ($public && $event->public_description)
            <p class="line-clamp-2 text-sm text-zinc-500">{{ $event->public_description }}</p>
        @endif
    </div>
</{{ $tag }}>
