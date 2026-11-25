@props(['date', 'size' => 'md', 'muted' => false])

{{-- Calendar-style date tile (month · day · weekday) for public cards. --}}
<span {{ $attributes->class([
    'flex shrink-0 flex-col items-center justify-center rounded-mci leading-none',
    'w-14 py-2' => $size === 'md',
    'w-11 py-1.5' => $size === 'sm',
    'bg-primary/10 text-primary' => ! $muted,
    'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' => $muted,
]) }} aria-label="{{ $date->translatedFormat('j F Y') }}">
    <span @class(['font-medium uppercase', 'text-[0.65rem]' => $size === 'md', 'text-[0.55rem]' => $size === 'sm'])>{{ $date->translatedFormat('M') }}</span>
    <span @class(['font-bold tabular-nums', 'text-2xl' => $size === 'md', 'text-lg' => $size === 'sm'])>{{ $date->format('j') }}</span>
    @if ($size === 'md')
        <span class="mt-0.5 text-[0.6rem] opacity-80">{{ $date->translatedFormat('D') }}</span>
    @endif
</span>
