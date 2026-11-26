@props(['key', 'heading', 'icon', 'active' => false, 'open' => true, 'badge' => null])

{{--
    Collapsible sidebar section. The open/closed state is remembered per
    person in this browser; the section holding the current page always opens.
--}}
<div x-data="{ open: $persist(@js((bool) $open)).as(@js('mci-nav-'.auth()->id().'-'.$key)) }"
     x-init="if (@js((bool) $active)) open = true"
     data-nav-section="{{ $key }}" class="grid">
    <button type="button" x-on:click="open = ! open" :aria-expanded="open.toString()" aria-controls="nav-{{ $key }}"
            class="group flex h-8 w-full items-center gap-2 rounded-lg px-3 text-xs font-medium text-zinc-500 transition-colors hover:bg-zinc-800/5 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/[7%] dark:hover:text-white">
        <flux:icon :name="$icon" variant="micro" class="shrink-0" />
        <span class="min-w-0 flex-1 truncate text-start">{{ $heading }}</span>
        @if ($badge)
            <span x-show="! open" x-cloak class="rounded-full bg-zinc-200 px-1.5 text-[0.6875rem] tabular-nums text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $badge }}</span>
        @endif
        <span class="shrink-0 transition-transform duration-150" :class="open ? '' : '-rotate-90'"><flux:icon name="chevron-down" variant="micro" /></span>
    </button>
    <div id="nav-{{ $key }}" x-show="open" x-collapse class="grid gap-0.5 pb-1 pt-0.5">
        {{ $slot }}
    </div>
</div>
