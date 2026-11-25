<div class="space-y-6">
    <x-ui.page-header :title="$eventId ? __('Edycja wydarzenia') : __('Nowe wydarzenie')">
        <x-slot:actions>
            <flux:button :href="route('events.index')" wire:navigate variant="ghost" icon="arrow-left">{{ __('Wydarzenia') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <flux:input wire:model.live.debounce.400ms="title" :label="__('Nazwa')" :placeholder="__('np. Dzień Otwarty ZSM')" maxlength="200" />

                <div class="space-y-2">
                    <flux:label>{{ __('Kategoria') }}</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($categories as $c)
                            <x-ui.pill wire:click="$set('category', '{{ $c->value }}')" :active="$category === $c->value"><flux:icon :name="$c->icon()" variant="micro" /> {{ $c->label() }}</x-ui.pill>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input type="date" wire:model.live="date" :label="__('Data')" />
                    @unless ($all_day)
                        <flux:input type="time" wire:model.live="start_time" :label="__('Godzina rozpoczęcia')" />
                    @endunless
                    <flux:input type="date" wire:model="end_date" :label="__('Data zakończenia (opcjonalnie)')" />
                    @unless ($all_day)
                        <flux:input type="time" wire:model="end_time" :label="__('Godzina zakończenia')" />
                    @endunless
                </div>
                <flux:switch wire:model.live="all_day" :label="__('Wydarzenie całodniowe')" />

                <flux:input wire:model.live.debounce.400ms="location" :label="__('Miejsce')" icon="map-pin" :placeholder="__('np. Aula, budynek A')" />

                <flux:textarea wire:model="description" :label="__('Opis wewnętrzny')" :description="__('Widoczny tylko dla zalogowanych osób z dostępem do wydarzenia.')" rows="4" />
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Kto zobaczy?') }}</flux:heading>
                <div class="space-y-2">
                    @foreach ($visibilities as $v)
                        @php($locked = $v === \App\Enums\ContentVisibility::Public && ! $canPublishPublic)
                        <button type="button" @unless ($locked) wire:click="$set('visibility', '{{ $v->value }}')" @endunless @disabled($locked)
                                @class([
                                    'flex w-full gap-3 rounded-mci border p-3 text-start transition-colors',
                                    'border-primary bg-primary/5' => $visibility === $v->value,
                                    'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' => $visibility !== $v->value,
                                    'cursor-not-allowed opacity-50' => $locked,
                                ])>
                            <flux:icon :name="$v->icon()" variant="mini" class="mt-0.5 shrink-0 text-zinc-500" />
                            <span>
                                <span class="block text-sm font-medium">{{ $v->label() }}</span>
                                <span class="block text-xs text-zinc-500">{{ $locked ? __('Wymaga uprawnienia do publikacji publicznej.') : $v->description() }}</span>
                            </span>
                        </button>
                    @endforeach
                    <flux:error name="visibility" />
                </div>

                @if ($visibility === 'public')
                    <flux:textarea wire:model.live.debounce.400ms="public_description" :label="__('Opis publiczny')" :description="__('Tylko ten tekst, nazwa, data i miejsce trafią na stronę publiczną.')" rows="4" />
                @endif

                @if ($eventId)
                    <flux:switch wire:model="cancelled" :label="__('Wydarzenie odwołane')" />
                @endif

                <flux:button type="submit" variant="primary" class="w-full">{{ __('Zapisz') }}</flux:button>
                @if ($eventId)
                    <flux:button wire:click="delete" wire:confirm="{{ __('Usunąć to wydarzenie?') }}" variant="ghost" icon="trash" class="w-full">{{ __('Usuń') }}</flux:button>
                @endif
            </flux:card>

            @if ($visibility === 'public' && $title !== '' && $date !== '')
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm font-medium text-zinc-500"><flux:icon name="eye" variant="micro" /> {{ __('Podgląd na stronie publicznej') }}</div>
                    @php($preview = new \App\Models\Event(['title' => $title, 'category' => $category, 'location' => $location ?: null, 'all_day' => $all_day, 'public_description' => $public_description ?: null, 'visibility' => 'public', 'status' => 'planned']))
                    @php($preview->starts_at = rescue(fn () => \Illuminate\Support\Carbon::parse($date.' '.($all_day ? '00:00' : ($start_time ?: '00:00'))), now(), false))
                    @php($preview->setRelation('school', app(\App\Support\CurrentSchool::class)->get()))
                    <x-event-card :event="$preview" public />
                </div>
            @endif
        </div>
    </form>
</div>
