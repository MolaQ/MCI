@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center gap-2 py-8 text-center') }}>
    <flux:icon :name="$icon" class="size-8 text-zinc-300 dark:text-zinc-600" />
    <flux:heading>{{ $title }}</flux:heading>
    @if ($description)
        <flux:text class="max-w-sm">{{ $description }}</flux:text>
    @endif
    {{ $slot }}
</div>
