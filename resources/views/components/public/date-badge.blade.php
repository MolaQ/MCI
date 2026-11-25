@props(['date', 'size' => 'md'])

{{-- Calendar-style date: month, day, weekday. Monochrome, tinted with the theme colour. --}}
<div {{ $attributes->class([
    'flex shrink-0 flex-col items-center justify-center rounded-mci border border-primary/15 bg-primary/[0.06] text-primary',
    'w-14 py-1.5' => $size === 'md',
    'w-20 py-3' => $size === 'lg',
]) }} aria-label="{{ $date->translatedFormat('l, j F Y') }}">
    <span @class(['font-semibold uppercase tracking-wide', 'text-[0.65rem]' => $size === 'md', 'text-xs' => $size === 'lg'])>{{ $date->translatedFormat('M') }}</span>
    <span @class(['font-bold leading-none tabular-nums', 'text-2xl' => $size === 'md', 'text-4xl' => $size === 'lg'])>{{ $date->format('j') }}</span>
    <span @class(['text-primary/70', 'text-[0.65rem]' => $size === 'md', 'text-xs' => $size === 'lg'])>{{ $date->translatedFormat('D') }}</span>
</div>
