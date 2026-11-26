<x-layouts::public :title="$event->title">
    <div class="mci-wrap space-y-6 py-10">
        <a href="{{ \App\Livewire\Publicly\Home::url('wydarzenia') }}" class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"><flux:icon name="arrow-left" variant="micro" /> {{ __('Wszystkie wydarzenia') }}</a>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] 2xl:grid-cols-[minmax(0,1fr)_24rem]">
            <flux:card class="mci-fade-up min-w-0 space-y-5">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge :icon="$event->category->icon()">{{ $event->category->label() }}</flux:badge>
                    @if ($event->status === 'cancelled')
                        <flux:badge color="red">{{ __('Odwołane') }}</flux:badge>
                    @endif
                </div>
                @if ($event->cover?->is_public)
                    <img src="{{ $event->cover->url() }}" alt="" width="{{ $event->cover->width }}" height="{{ $event->cover->height }}" class="aspect-video max-h-[28rem] w-full rounded-mci object-cover" />
                @endif
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $event->title }}</h1>
                <div class="grid gap-3 text-zinc-700 sm:grid-cols-2 dark:text-zinc-200">
                    <div class="flex items-center gap-2"><flux:icon name="calendar" variant="mini" class="text-zinc-400" /> {{ $event->timeLabel() }}</div>
                    @if ($event->location)
                        <div class="flex items-center gap-2"><flux:icon name="map-pin" variant="mini" class="text-zinc-400" /> {{ $event->location }}</div>
                    @endif
                    <a href="{{ route('public.school', $event->school->slug) }}" class="flex items-center gap-2 hover:text-primary"><x-app.school-badge :school="$event->school" size="sm" /> {{ $event->school->name }}</a>
                </div>
                @if ($event->public_description)
                    <div class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $event->public_description }}</div>
                @endif
                <div class="flex flex-wrap gap-2">
                    @if (app(\App\Support\Features::class)->enabled(\App\Enums\Feature::EventIcs, $event->school_id))
                        <flux:button :href="route('public.event.ics', $event)" icon="calendar-days">{{ __('Dodaj do kalendarza') }}</flux:button>
                    @endif
                    <flux:button :href="route('public.school', $event->school->slug)" variant="ghost" icon="building-library">{{ __('Strona szkoły') }}</flux:button>
                </div>
            </flux:card>
            <x-public.more-from-school :school="$event->school" :more="$more" />
        </div>
    </div>
</x-layouts::public>
