@props(['label', 'value', 'icon' => null, 'href' => null])

@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class('block rounded-mci border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900'.($href ? ' hover:border-primary' : '')) }}>
    <div class="flex items-center justify-between gap-2">
        <flux:text size="sm">{{ $label }}</flux:text>
        @if ($icon)
            <flux:icon :name="$icon" variant="micro" class="text-zinc-400" />
        @endif
    </div>
    <div class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</div>
</{{ $tag }}>
