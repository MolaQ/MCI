<div class="space-y-6">
    <x-ui.page-header :title="__('Wydarzenia')" :subtitle="__('Wydarzenia Twojej szkoły, międzyszkolne i publiczne.')">
        <x-slot:actions>
            <flux:button :href="route('home')" variant="ghost" icon="globe-alt">{{ __('Strona publiczna') }}</flux:button>
            @if ($canCreate)
                <flux:button :href="route('events.create')" wire:navigate variant="primary" icon="plus">{{ __('Nowe wydarzenie') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            @foreach (['school' => __('Moja szkoła'), 'partners' => __('Inne szkoły'), 'public' => __('Publiczne'), 'all' => __('Wszystkie')] as $key => $label)
                <x-ui.pill wire:click="$set('scope', '{{ $key }}')" :active="$scope === $key">{{ $label }}</x-ui.pill>
            @endforeach
            <span class="mx-1 hidden w-px bg-zinc-200 sm:block dark:bg-zinc-700"></span>
            @foreach (['upcoming' => __('Nadchodzące'), 'week' => __('Ten tydzień'), 'month' => __('Ten miesiąc'), 'past' => __('Minione')] as $key => $label)
                <x-ui.pill wire:click="$set('when', '{{ $key }}')" :active="$when === $key">{{ $label }}</x-ui.pill>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.pill wire:click="$set('category', '')" :active="$category === ''">{{ __('Każda kategoria') }}</x-ui.pill>
            @foreach ($categories as $c)
                <x-ui.pill wire:click="$set('category', '{{ $c->key }}')" :active="$category === $c->key"><flux:icon :name="$c->icon()" variant="micro" /> {{ $c->label() }}</x-ui.pill>
            @endforeach
        </div>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        @forelse ($this->events as $event)
            <x-event-card :event="$event" :href="route('events.show', $event)" wire:navigate wire:key="e-{{ $event->id }}" />
        @empty
            <flux:card class="md:col-span-2">
                <x-ui.empty-state icon="calendar" :title="__('Brak wydarzeń')" :description="__('Zmień filtry albo dodaj pierwsze wydarzenie.')" />
            </flux:card>
        @endforelse
    </div>

    {{ $this->events->links() }}
</div>
