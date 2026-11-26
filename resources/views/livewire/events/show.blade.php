<div class="space-y-6">
    <div class="flex items-center justify-between gap-2">
        <flux:button :href="route('events.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">{{ __('Wydarzenia') }}</flux:button>
        <div class="flex gap-2">
            @if ($event->isPublic())
                <flux:button :href="route('public.event', $event)" size="sm" variant="ghost" icon="globe-alt">{{ __('Wersja publiczna') }}</flux:button>
            @endif
            @can('update', $event)
                <flux:button :href="route('events.edit', $event)" wire:navigate size="sm" icon="pencil-square">{{ __('Edytuj') }}</flux:button>
            @endcan
        </div>
    </div>

    <flux:card class="space-y-5">
        <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
            <flux:badge size="sm" :icon="$event->category->icon()">{{ $event->category->label() }}</flux:badge>
            <flux:badge size="sm" :icon="$event->visibility->icon()">{{ $event->visibility->label() }}</flux:badge>
            @if ($event->status === 'cancelled')
                <flux:badge size="sm" color="red">{{ __('Odwołane') }}</flux:badge>
            @endif
        </div>
        @if ($event->cover)
            <img src="{{ $event->cover->url() }}" alt="" width="{{ $event->cover->width }}" height="{{ $event->cover->height }}" class="aspect-video max-h-[28rem] w-full rounded-mci object-cover" />
        @endif
        <flux:heading size="xl" level="1">{{ $event->title }}</flux:heading>
        <div class="grid gap-3 text-sm sm:grid-cols-2">
            <div class="flex items-center gap-2"><flux:icon name="calendar" variant="mini" class="text-zinc-400" /> {{ $event->timeLabel() }}</div>
            @if ($event->location)
                <div class="flex items-center gap-2"><flux:icon name="map-pin" variant="mini" class="text-zinc-400" /> {{ $event->location }}</div>
            @endif
            <div class="flex items-center gap-2"><x-app.school-badge :school="$event->school" size="sm" /> {{ $event->school->name }}</div>
            @if ($event->owner)
                <div class="flex items-center gap-2"><flux:icon name="user" variant="mini" class="text-zinc-400" /> {{ $event->owner->name }}</div>
            @endif
        </div>
        @if ($event->description)
            <div class="max-w-4xl whitespace-pre-line text-zinc-700 dark:text-zinc-200">{{ $event->description }}</div>
        @endif
        @if ($event->isPublic() && $event->public_description)
            <div class="rounded-mci bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                <div class="mb-1 flex items-center gap-1 font-medium text-zinc-500"><flux:icon name="globe-alt" variant="micro" /> {{ __('Opis publiczny') }}</div>
                <div class="whitespace-pre-line">{{ $event->public_description }}</div>
            </div>
        @endif
        <flux:button :href="route('public.event.ics', $event)" size="sm" variant="ghost" icon="calendar-days" class="{{ $event->isPublic() ? '' : 'hidden' }}">{{ __('Dodaj do kalendarza (.ics)') }}</flux:button>
    </flux:card>
</div>
