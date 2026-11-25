<a href="{{ route('dashboard') }}" wire:navigate {{ $attributes->class('flex items-center gap-2 min-w-0') }}>
    @if ($appearance->logoUrl())
        <img src="{{ $appearance->logoUrl() }}" alt="" class="h-8 w-auto" />
    @else
        <span class="flex size-8 shrink-0 items-center justify-center rounded-mci bg-primary text-[0.625rem] font-bold tracking-tight text-primary-foreground">
            {{ \Illuminate\Support\Str::limit($appearance->shortName(), 4, '') }}
        </span>
    @endif
    <span class="truncate text-sm font-semibold text-zinc-800 dark:text-white">{{ $appearance->shortName() }}</span>
</a>
