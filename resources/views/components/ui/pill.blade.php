@props(['active' => false, 'count' => null])

{{-- Button-driven filter pill (docs/ARCHITECTURE.md §12.2). --}}
<button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition-colors duration-150',
    'border-primary bg-primary text-primary-foreground' => $active,
    'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700' => ! $active,
]) }}>
    {{ $slot }}
    @if ($count !== null)
        <span @class(['tabular-nums text-xs', 'opacity-80' => $active, 'text-zinc-400' => ! $active])>{{ $count }}</span>
    @endif
</button>
