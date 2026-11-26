<div class="space-y-6">
    <x-ui.page-header :title="$projectId ? __('Edycja projektu') : __('Nowy projekt')" :subtitle="__('Wydarzenie, grant, Erasmus+, inwestycja, dzień otwarty lub konkurs: jeden projekt, jeden zespół.')">
        <x-slot:actions>
            <flux:button :href="$projectId ? route('projects.show', $projectId) : route('projects.index')" wire:navigate variant="ghost" icon="arrow-left">{{ $projectId ? __('Projekt') : __('Projekty') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3 2xl:grid-cols-4">
        <div class="min-w-0 space-y-6 lg:col-span-2 2xl:col-span-3">
            <flux:card class="space-y-5">
                <flux:input wire:model="name" :label="__('Nazwa projektu')" :placeholder="__('np. Dzień Otwarty 2027')" maxlength="200" required />

                <div class="space-y-2">
                    <flux:label>{{ __('Rodzaj') }}</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($kinds as $k)
                            <x-ui.pill wire:click="$set('kind', '{{ $k->value }}')" :active="$kind === $k->value"><flux:icon :name="$k->icon()" variant="micro" /> {{ $k->label() }}</x-ui.pill>
                        @endforeach
                    </div>
                </div>

                <x-ui.editor property="description" :label="__('Opis')" :placeholder="__('Cel, zakres, ustalenia… Wpisz @, aby wspomnieć osobę lub grupę.')" />

                <flux:textarea wire:model="budget_notes" :label="__('Budżet i zasoby')" :placeholder="__('np. 2 000 zł z rady rodziców, sala gimnastyczna, nagłośnienie')" rows="3" maxlength="5000" />

                @if ($covers)
                    <x-ui.image-editor model="cover" :label="__('Okładka')" :current="$removeCover ? null : $this->project?->cover?->url()" :remove="$this->project?->cover && ! $removeCover ? 'dropCover' : null"
                                       aspect="3:1" :presets="['3:1', '16:9', '4:3', 'free']" :max-size="2000"
                                       :hint="__('Grafika na liście i w nagłówku projektu. Kadrowanie i usuwanie tła w edytorze.')" />
                @endif
            </flux:card>

            <flux:card class="space-y-5" data-project-team>
                <div>
                    <flux:heading size="lg">{{ __('Zespół') }}</flux:heading>
                    <flux:text>{{ __('Koordynator prowadzi projekt. Zespół widzi projekt, odhacza kamienie milowe i dostaje powiadomienia.') }}</flux:text>
                </div>

                <div class="space-y-2">
                    <flux:label>{{ __('Koordynator') }}</flux:label>
                    @if ($this->coordinator)
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 py-0.5 ps-1 pe-3 text-sm text-primary">
                                <flux:avatar size="xs" :initials="$this->coordinator->initials()" class="!size-5" /> {{ $this->coordinator->name }}
                            </span>
                        </div>
                    @endif
                    <div class="relative">
                        <flux:input wire:model.live.debounce.250ms="coordinatorSearch" icon="arrows-right-left" size="sm" :placeholder="__('Zmień koordynatora — wpisz imię lub nazwisko')" autocomplete="off" />
                        @if ($this->coordinatorResults->isNotEmpty())
                            <div class="mci-fade-up absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-mci border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
                                @foreach ($this->coordinatorResults as $person)
                                    <button type="button" wire:key="cr-{{ $person->id }}" wire:click="setCoordinator({{ $person->id }})" class="flex w-full items-center gap-3 px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                        <flux:avatar size="xs" :initials="$person->initials()" /> <span class="flex-1">{{ $person->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <flux:error name="coordinator_id" />
                </div>

                @if ($this->groups->isNotEmpty())
                    <div class="space-y-2">
                        <flux:label>{{ __('Grupy') }}</flux:label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->groups as $group)
                                <x-ui.pill wire:key="pg-{{ $group->id }}" wire:click="toggleMember('group:{{ $group->id }}')" :active="in_array('group:'.$group->id, $members)">
                                    <span class="size-2 rounded-full" style="background: {{ $group->color ?: '#a1a1aa' }}"></span> {{ $group->name }}
                                    @if (count($partners))<span class="text-xs opacity-60">{{ $group->school?->short_name }}</span>@endif
                                </x-ui.pill>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($this->canAddRoles)
                    <div class="space-y-2">
                        <flux:label>{{ __('Role w szkole') }}</flux:label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($roles as $role)
                                <x-ui.pill wire:click="toggleMember('role:{{ $role->value }}')" :active="in_array('role:'.$role->value, $members)"><flux:icon name="identification" variant="micro" /> {{ $role->label() }}</x-ui.pill>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="space-y-2">
                    <flux:label>{{ __('Osoby') }}</flux:label>
                    <div class="relative">
                        <flux:input wire:model.live.debounce.250ms="peopleSearch" icon="user-plus" :placeholder="__('Dodaj osobę — wpisz imię lub nazwisko')" autocomplete="off" />
                        @if ($this->peopleResults->isNotEmpty())
                            <div class="mci-fade-up absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-mci border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
                                @foreach ($this->peopleResults as $person)
                                    <button type="button" wire:key="pp-{{ $person->id }}" wire:click="addPerson({{ $person->id }})" class="flex w-full items-center gap-3 px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                        <flux:avatar size="xs" :initials="$person->initials()" /> <span class="flex-1">{{ $person->name }}</span> <flux:icon name="plus" variant="micro" class="text-zinc-400" />
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if ($this->selectedPeople->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->selectedPeople as $person)
                                <span wire:key="ps-{{ $person->id }}" class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 py-0.5 ps-1 pe-2 text-sm text-primary">
                                    <flux:avatar size="xs" :initials="$person->initials()" class="!size-5" /> {{ $person->name }}
                                    <button type="button" wire:click="toggleMember('user:{{ $person->id }}')" aria-label="{{ __('Usuń :name', ['name' => $person->name]) }}"><flux:icon name="x-mark" variant="micro" /></button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <flux:error name="members" />
            </flux:card>

            @if ($this->partnerOptions->isNotEmpty())
                <flux:card class="space-y-3" data-project-partners>
                    <div>
                        <flux:heading size="lg">{{ __('Szkoły partnerskie') }}</flux:heading>
                        <flux:text>{{ __('Projekt międzyszkolny: partnerzy widzą projekt, mogą być w zespole i powiązać swoje zadania oraz wnioski.') }}</flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->partnerOptions as $s)
                            <x-ui.pill wire:key="sp-{{ $s->id }}" wire:click="togglePartner({{ $s->id }})" :active="in_array($s->id, $partners)"><flux:icon name="building-library" variant="micro" /> {{ $s->short_name }}</x-ui.pill>
                        @endforeach
                    </div>
                    <flux:error name="partners" />
                </flux:card>
            @endif
        </div>

        <div class="space-y-6">
            <flux:card class="sticky top-4 space-y-5">
                <div class="space-y-2">
                    <flux:label>{{ __('Status') }}</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($statuses as $s)
                            <x-ui.pill wire:click="$set('status', '{{ $s->value }}')" :active="$status === $s->value"><flux:icon :name="$s->icon()" variant="micro" /> {{ $s->label() }}</x-ui.pill>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <flux:input type="date" wire:model="starts_on" :label="__('Początek')" />
                    <flux:input type="date" wire:model="ends_on" :label="__('Koniec')" />
                </div>
                <flux:error name="ends_on" />

                <flux:radio.group wire:model="visibility" :label="__('Kto widzi projekt')">
                    <flux:radio value="school" :label="__('Cała szkoła (i szkoły partnerskie)')" />
                    <flux:radio value="team" :label="__('Tylko zespół projektu')" />
                    <flux:radio value="public" :label="__('Publicznie')" :description="__('Nazwa, termin i postęp także na portalu publicznym i tablicy TV. Zespół, budżet i opis zostają wewnętrzne.')" />
                </flux:radio.group>

                @if ($errors->any())
                    <div role="alert" class="space-y-1 rounded-mci border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                        <p class="flex items-center gap-1.5 font-medium"><flux:icon name="exclamation-triangle" variant="micro" /> {{ __('Projekt nie został zapisany:') }}</p>
                        <ul class="list-disc space-y-0.5 ps-5">
                            @foreach (array_unique($errors->all()) as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <flux:button type="submit" variant="primary" icon="check" class="w-full">{{ $projectId ? __('Zapisz zmiany') : __('Utwórz projekt') }}</flux:button>
            </flux:card>
        </div>
    </form>
</div>
