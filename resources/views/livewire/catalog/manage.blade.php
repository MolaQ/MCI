<div class="space-y-6">
    <x-ui.page-header :title="__('Kategorie i typy')" :subtitle="$scope === 'site'
        ? __('Lista wspólna dla wszystkich szkół. Szkoły mogą ją uzupełniać o własne pozycje i ukrywać to, czego nie używają (jeśli pozwalają na to Funkcje).')
        : __('Kategorie wydarzeń i typy grup w szkole :school. Pozycje wspólne pochodzą od administratora portalu; możesz je ukryć i dodać własne.', ['school' => $schoolName])">
        <x-slot:actions>
            @if ($mayCustomise)
                <flux:button variant="primary" icon="plus" wire:click="startCreate">{{ __('Dodaj') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach ($kinds as $k)
            <x-ui.pill wire:click="setKind('{{ $k->value }}')" :active="$kind === $k->value" role="tab">{{ $k->label() }}</x-ui.pill>
        @endforeach
    </div>

    <flux:error name="term" />

    @if ($scope === 'school' && ! $mayCustomise)
        <flux:callout icon="lock-closed" color="zinc" :heading="__('Własna lista jest wyłączona')"
            :text="__('Administrator portalu nie pozwala szkołom zmieniać tej listy. Szkoła korzysta z listy wspólnej poniżej.')" />
    @endif

    @if ($scope === 'school')
        <flux:card class="space-y-1">
            <div class="mb-2 flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('Własne pozycje szkoły') }}</flux:heading>
            </div>
            @forelse ($own as $i => ['term' => $term, 'usage' => $usage])
                @include('livewire.catalog.row', ['term' => $term, 'usage' => $usage, 'editable' => $mayCustomise, 'first' => $i === 0, 'last' => $i === $own->count() - 1, 'isHidden' => false])
            @empty
                <flux:text>{{ __('Szkoła nie ma jeszcze własnych pozycji.') }}</flux:text>
            @endforelse
        </flux:card>
    @endif

    <flux:card class="space-y-1">
        <flux:heading size="lg" class="mb-2">{{ $scope === 'site' ? $kindEnum->label() : __('Pozycje wspólne portalu') }}</flux:heading>
        @foreach ($site as $i => ['term' => $term, 'usage' => $usage])
            @include('livewire.catalog.row', ['term' => $term, 'usage' => $usage, 'editable' => $scope === 'site', 'first' => $i === 0, 'last' => $i === $site->count() - 1, 'isHidden' => in_array($term->key, $hidden, true)])
        @endforeach
    </flux:card>

    <flux:modal name="term" class="w-full max-w-lg" x-on:close="$wire.cancel()">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edytuj pozycję') : __('Nowa pozycja: :kind', ['kind' => $kindEnum->singular()]) }}</flux:heading>

            <flux:input wire:model="name" :label="__('Nazwa')" maxlength="60" required autofocus />

            <flux:field>
                <flux:label>{{ __('Ikona') }}</flux:label>
                <div class="grid grid-cols-8 gap-1.5 sm:grid-cols-11" role="radiogroup" aria-label="{{ __('Ikona') }}">
                    @foreach ($icons as $ic)
                        <button type="button" wire:click="$set('icon', '{{ $ic }}')" role="radio" aria-checked="{{ $icon === $ic ? 'true' : 'false' }}" title="{{ $ic }}"
                            @class(['flex aspect-square items-center justify-center rounded-mci border transition-colors',
                                'border-primary bg-primary text-primary-foreground' => $icon === $ic,
                                'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800' => $icon !== $ic])>
                            <flux:icon :name="$ic" variant="mini" />
                        </button>
                    @endforeach
                </div>
                <flux:error name="icon" />
            </flux:field>

            @if ($kindEnum->hasColor())
                <flux:field>
                    <flux:label>{{ __('Kolor znacznika') }}</flux:label>
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model.live="color" class="h-10 w-14 cursor-pointer rounded-mci border border-zinc-200 bg-white p-1 dark:border-zinc-600 dark:bg-zinc-800" />
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 px-2.5 py-0.5 text-sm dark:border-zinc-700">
                            <span class="size-2.5 rounded-full" style="background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#64748b' }}"></span>
                            <flux:icon :name="$icon" variant="micro" /> {{ $name ?: __('Podgląd') }}
                        </span>
                    </div>
                    <flux:error name="color" />
                </flux:field>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Anuluj') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Zapisz') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
