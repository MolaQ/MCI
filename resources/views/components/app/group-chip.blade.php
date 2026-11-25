@props(['group'])

<span {{ $attributes->class('inline-flex min-w-0 items-center gap-2') }}>
    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $group->displayColor() }}"></span>
    <span class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $group->name }}</span>
</span>
