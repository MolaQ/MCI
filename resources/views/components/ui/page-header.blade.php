@props(['title', 'subtitle' => null, 'image' => null])

<div {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="flex min-w-0 items-center gap-3">
        @if ($image)
            <img src="{{ $image }}" alt="" class="size-12 shrink-0 rounded-mci bg-white object-contain ring-1 ring-zinc-200 dark:ring-zinc-700" />
        @endif
        <div class="min-w-0">
            <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
            @if ($subtitle)
                <flux:subheading class="mt-1">{{ $subtitle }}</flux:subheading>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
