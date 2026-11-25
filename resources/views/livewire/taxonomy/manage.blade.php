<div class="space-y-6">
    <x-ui.page-header :title="$school ? __('Kategorie i typy grup') : __('Kategorie i typy grup — cała witryna')"
                      :subtitle="$school
                        ? __('Pozycje używane w szkole :name. Zmiany trafiają do logów szkoły.', ['name' => $school->short_name])
                        : __('Ogólne pozycje dostępne we wszystkich szkołach. Zmiany trafiają do dziennika witryny.')">
        <x-slot:actions>
            @if ($canCreate)
                <flux:button variant="primary" icon="plus" wire:click="startCreate">{{ __('Dodaj') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach ($kinds as $k)
            <x-ui.pill wire:click="setKind('{{ $k->value }}')" :active="$termKind === $k" role="tab" wire:key="kind-{{ $k->value }}">
                <flux:icon :name="$k->defaultIcon()" variant="micro" /> {{ $k->label() }}
            </x-ui.pill>
        @endforeach
    </div>

    @if ($school && ! $customAllowed)
        <flux:callout icon="lock-closed" color="zinc">
            <flux:callout.heading>{{ __('Szkoła używa tylko ogólnych pozycji') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Własne pozycje i ukrywanie ogólnych włącza się w funkcjach szkoły („:f”).', ['f' => $termKind->feature()->label()]) }}
                @if ($canSwitchFeature)
                    <flux:callout.link :href="route('admin.school.features')" wire:navigate>{{ __('Przejdź do funkcji szkoły') }}</flux:callout.link>
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    @php($global = $this->terms->filter->isGlobal()->values())
    @php($own = $this->terms->reject->isGlobal()->values())

    @foreach (array_filter([
        'global' => [$school ? __('Ogólne (cała witryna)') : null, $global, $school ? __('Ukryj pozycje, których szkoła nie używa. Nazwy zmienia administrator witryny.') : null],
        'own' => $school ? [__('Własne pozycje szkoły'), $own, __('Widoczne tylko w tej szkole.')] : null,
    ]) as $section => [$heading, $rows, $hint])
        <flux:card class="space-y-1 !p-0" wire:key="section-{{ $section }}">
            @if ($heading)
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-zinc-100 px-5 py-4 dark:border-zinc-700/60">
                    <flux:heading size="lg">{{ $heading }}</flux:heading>
                    @if ($hint)<flux:text size="sm">{{ $hint }}</flux:text>@endif
                </div>
            @endif

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-700/60" data-terms="{{ $section }}">
                @forelse ($rows as $i => $term)
                    @php($editable = auth()->user()->can('update', $term))
                    @php($isHidden = in_array($term->id, $hidden, true))
                    @php($protected = $catalog->isProtected($term))
                    @php($used = $this->usage[$term->id] ?? 0)
                    <li wire:key="term-{{ $term->id }}" data-term="{{ $term->key }}"
                        @class(['mci-fade-up flex items-center gap-3 px-5 py-3', 'opacity-60' => ! $term->is_active || $isHidden])>
                        @if ($editable)
                            <div class="flex shrink-0 flex-col">
                                <button type="button" wire:click="move({{ $term->id }}, 'up')" @disabled($i === 0)
                                        class="rounded p-0.5 text-zinc-400 hover:text-zinc-700 disabled:invisible dark:hover:text-zinc-200" aria-label="{{ __('Wyżej') }}">
                                    <flux:icon.chevron-up variant="micro" />
                                </button>
                                <button type="button" wire:click="move({{ $term->id }}, 'down')" @disabled($i === $rows->count() - 1)
                                        class="rounded p-0.5 text-zinc-400 hover:text-zinc-700 disabled:invisible dark:hover:text-zinc-200" aria-label="{{ __('Niżej') }}">
                                    <flux:icon.chevron-down variant="micro" />
                                </button>
                            </div>
                        @endif

                        <span class="flex size-9 shrink-0 items-center justify-center rounded-mci bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                              @if ($termKind->usesColor()) style="box-shadow: inset 0 -3px 0 {{ $term->color() }}" @endif>
                            <flux:icon :name="$term->icon()" variant="mini" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $term->name }}</span>
                                @if ($term->is_featured)
                                    <flux:badge size="sm" icon="star">{{ __('wyróżniana') }}</flux:badge>
                                @endif
                                @unless ($term->is_active)
                                    <flux:badge size="sm" color="zinc" icon="archive-box">{{ __('w archiwum') }}</flux:badge>
                                @endunless
                                @if ($isHidden)
                                    <flux:badge size="sm" color="zinc" icon="eye-slash">{{ __('ukryta w szkole') }}</flux:badge>
                                @endif
                                @if ($protected)
                                    <flux:badge size="sm" color="zinc" icon="lock-closed">{{ __('zawsze dostępna') }}</flux:badge>
                                @endif
                            </div>
                            <flux:text size="sm">{{ $used === 0 ? __('Nieużywana') : trans_choice('Używana :count raz|Używana :count razy|Używana :count razy', $used) }}</flux:text>
                        </div>

                        @php($canHide = $school && $term->isGlobal() && auth()->user()->can('hide', [$term, $school]))
                        @if ($editable || $canHide)
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Działania: :name', ['name' => $term->name])" />
                                <flux:menu>
                                    @if ($editable)
                                        <flux:menu.item icon="pencil-square" wire:click="startEdit({{ $term->id }})">{{ __('Edytuj') }}</flux:menu.item>
                                    @endif
                                    @if ($canHide)
                                        <flux:menu.item :icon="$isHidden ? 'eye' : 'eye-slash'" wire:click="toggleHidden({{ $term->id }})">
                                            {{ $isHidden ? __('Pokaż w szkole') : __('Ukryj w szkole') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($editable && ! $protected)
                                        <flux:menu.item :icon="$term->is_active ? 'archive-box' : 'arrow-uturn-left'" wire:click="toggleActive({{ $term->id }})">
                                            {{ $term->is_active ? __('Archiwizuj') : __('Przywróć') }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $term->id }})"
                                                        wire:confirm="{{ __('Usunąć „:name”? Tej operacji nie można cofnąć.', ['name' => $term->name]) }}">
                                            {{ __('Usuń') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-6">
                        <x-ui.empty-state :icon="$termKind->defaultIcon()" :title="__('Brak pozycji')"
                                          :description="$canCreate ? __('Dodaj pierwszą przyciskiem „Dodaj”.') : null" class="!py-2" />
                    </li>
                @endforelse
            </ul>
        </flux:card>
    @endforeach
    <flux:error name="term" />

    <flux:modal name="term-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edycja: :name', ['name' => $editing?->name]) : __('Nowa pozycja: :kind', ['kind' => $termKind->singular()]) }}</flux:heading>
                <flux:text size="sm">{{ $school ? __('Pozycja szkoły :name.', ['name' => $school->short_name]) : __('Pozycja ogólna — widoczna we wszystkich szkołach.') }}</flux:text>
            </div>

            <flux:input wire:model.live.debounce.300ms="name" :label="__('Nazwa')" maxlength="60" required autofocus />

            <flux:field>
                <flux:label>{{ __('Ikona') }}</flux:label>
                <div class="grid grid-cols-7 gap-1.5 sm:grid-cols-9" role="radiogroup" aria-label="{{ __('Ikona') }}">
                    @foreach (\App\Enums\TermKind::ICONS as $glyph)
                        <button type="button" wire:click="pickIcon('{{ $glyph }}')" role="radio" aria-checked="{{ $icon === $glyph ? 'true' : 'false' }}" title="{{ $glyph }}"
                                @class([
                                    'flex aspect-square items-center justify-center rounded-mci border transition-colors duration-150',
                                    'border-primary bg-primary text-primary-foreground' => $icon === $glyph,
                                    'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800' => $icon !== $glyph,
                                ])>
                            <flux:icon :name="$glyph" variant="mini" />
                        </button>
                    @endforeach
                </div>
                <flux:error name="icon" />
            </flux:field>

            @if ($termKind->usesColor())
                <flux:field>
                    <flux:label>{{ __('Kolor znacznika grupy') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="color" class="h-10 w-14 cursor-pointer rounded-mci border border-zinc-200 bg-white p-1 dark:border-zinc-600 dark:bg-zinc-800" />
                        <flux:input wire:model.live="color" placeholder="#475569" class="max-w-32" />
                    </div>
                    <flux:error name="color" />
                </flux:field>
            @endif

            @if ($termKind->usesFeatured())
                <flux:switch wire:model="is_featured" :label="__('Wyróżniaj na stronie głównej')"
                             :description="__('Najbliższe wydarzenia z tej kategorii trafiają do sekcji „Wyróżnione”.')" />
            @endif

            <div class="flex items-center gap-3 rounded-mci border border-dashed border-zinc-200 p-3 text-sm dark:border-zinc-700">
                <span class="text-zinc-500">{{ __('Podgląd') }}:</span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1 font-medium text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200">
                    @if ($termKind->usesColor())
                        <span class="size-2.5 rounded-full" style="background-color: {{ \App\Support\Appearance::sanitizeHex($color, '#475569') }}"></span>
                    @endif
                    <flux:icon :name="in_array($icon, \App\Enums\TermKind::ICONS, true) ? $icon : 'tag'" variant="micro" /> {{ $name !== '' ? $name : __('Nazwa') }}
                </span>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Anuluj') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Zapisz') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
