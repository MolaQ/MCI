<div class="space-y-6">
    <x-ui.page-header :title="__('Nowy komunikat')" :subtitle="__('Trafia tylko do wybranych odbiorców (suma grup, ról i osób) oraz osób wspomnianych przez @.')">
        <x-slot:actions>
            <flux:button :href="route('communication.index')" wire:navigate variant="ghost" icon="arrow-left">{{ __('Komunikaty') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <flux:input wire:model="title" :label="__('Tytuł')" :placeholder="__('np. Zmiana planu rady pedagogicznej')" maxlength="200" />

                <x-ui.editor property="body" :label="__('Treść')" :placeholder="__('Napisz komunikat… Wpisz @, aby wspomnieć osobę lub grupę.')" />

                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:label>{{ __('Obrazy') }} <span class="font-normal text-zinc-400">({{ count($images) }}/{{ config('mci.media.max_post_images') }})</span></flux:label>
                        @if (count($images) < config('mci.media.max_post_images'))
                            <x-ui.image-editor model="newImage" gallery :presets="['free', '16:9', '4:3', '1:1']" :max-size="2000" :background="false" />
                        @endif
                    </div>
                    @if ($images)
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($images as $i => $image)
                                <div wire:key="img-{{ $i }}-{{ $image->getFilename() }}" class="group relative overflow-hidden rounded-mci border border-zinc-200 dark:border-zinc-700">
                                    <img src="{{ $image->temporaryUrl() }}" alt="" class="aspect-video w-full object-cover" />
                                    <div class="absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-gradient-to-t from-black/60 p-1.5">
                                        @if ($i > 0)
                                            <button type="button" wire:click="moveImage({{ $i }}, -1)" class="rounded bg-white/90 p-1 text-zinc-700" title="{{ __('Wcześniej') }}"><flux:icon.chevron-left variant="micro" /></button>
                                        @endif
                                        @if ($i < count($images) - 1)
                                            <button type="button" wire:click="moveImage({{ $i }}, 1)" class="rounded bg-white/90 p-1 text-zinc-700" title="{{ __('Później') }}"><flux:icon.chevron-right variant="micro" /></button>
                                        @endif
                                        <button type="button" wire:click="removeImage({{ $i }})" class="rounded bg-white/90 p-1 text-red-600" title="{{ __('Usuń') }}"><flux:icon.trash variant="micro" /></button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <flux:error name="newImage" />
                    <flux:error name="images" />
                </div>

                <div class="space-y-2">
                    <flux:label>{{ __('Priorytet') }}</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($priorities as $p)
                            <x-ui.pill wire:click="$set('priority', '{{ $p->value }}')" :active="$priority === $p->value">
                                <flux:icon :name="$p->icon()" variant="micro" /> {{ $p->label() }}
                            </x-ui.pill>
                        @endforeach
                    </div>
                    <flux:text size="sm">{{ __('Ważne i pilne komunikaty oraz wzmianki trafiają też do dzwonka powiadomień.') }}</flux:text>
                </div>
            </flux:card>

            <flux:card class="space-y-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Odbiorcy') }}</flux:heading>
                        <flux:text>{{ __('Kliknij, aby zaznaczyć. Każda osoba dostaje komunikat tylko raz.') }}</flux:text>
                    </div>
                    @if ($audience)
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearAudience">{{ __('Wyczyść') }}</flux:button>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($this->canTargetSchool)
                        <x-ui.pill wire:click="toggle('everyone')" :active="in_array('everyone', $audience)">
                            <flux:icon name="building-library" variant="micro" /> {{ __('Cała szkoła') }}
                        </x-ui.pill>
                        @foreach ($roles as $role)
                            <x-ui.pill wire:click="toggle('role:{{ $role->value }}')" :active="in_array('role:'.$role->value, $audience)">
                                {{ $role->label() }}
                            </x-ui.pill>
                        @endforeach
                    @endif
                    <x-ui.pill wire:click="selectMyGroups">
                        <flux:icon name="sparkles" variant="micro" /> {{ __('Moje grupy') }}
                    </x-ui.pill>
                </div>

                <div class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:label>{{ __('Grupy') }}</flux:label>
                        @if ($this->groups->count() > 12)
                            <flux:input wire:model.live.debounce.200ms="groupFilter" size="sm" icon="magnifying-glass" :placeholder="__('Filtruj')" class="max-w-48" />
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($this->groups->filter(fn ($g) => $groupFilter === '' || str_contains(mb_strtolower($g->name), mb_strtolower($groupFilter))) as $group)
                            <x-ui.pill wire:key="g-{{ $group->id }}" wire:click="toggle('group:{{ $group->id }}')" :active="in_array('group:'.$group->id, $audience)">
                                <span class="size-2 rounded-full" style="background: {{ $group->color ?: '#a1a1aa' }}"></span>
                                {{ $group->name }}
                            </x-ui.pill>
                        @empty
                            <flux:text size="sm">{{ __('Nie możesz wysyłać do żadnej grupy — dodaj odbiorców imiennie.') }}</flux:text>
                        @endforelse
                    </div>
                    <flux:checkbox wire:model.live="includeSubgroups" :label="__('Uwzględnij podgrupy zaznaczonych grup')" />
                </div>

                <div class="space-y-2">
                    <flux:label>{{ __('Osoby') }}</flux:label>
                    <div class="relative">
                        <flux:input wire:model.live.debounce.250ms="peopleSearch" icon="user-plus" :placeholder="__('Dodaj osobę — wpisz imię lub nazwisko')" autocomplete="off" />
                        @if ($this->peopleResults->isNotEmpty())
                            <div class="mci-fade-up absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-mci border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
                                @foreach ($this->peopleResults as $person)
                                    <button type="button" wire:key="pr-{{ $person->id }}" wire:click="addPerson({{ $person->id }})"
                                            class="flex w-full items-center gap-3 px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                        <flux:avatar size="xs" :initials="$person->initials()" />
                                        <span class="flex-1">{{ $person->name }}</span>
                                        <flux:icon name="plus" variant="micro" class="text-zinc-400" />
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if ($this->selectedPeople->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->selectedPeople as $person)
                                <span wire:key="sp-{{ $person->id }}" class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 py-0.5 ps-1 pe-2 text-sm text-primary">
                                    <flux:avatar size="xs" :initials="$person->initials()" class="!size-5" />
                                    {{ $person->name }}
                                    <button type="button" wire:click="toggle('user:{{ $person->id }}')" aria-label="{{ __('Usuń :name', ['name' => $person->name]) }}">
                                        <flux:icon name="x-mark" variant="micro" />
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <flux:error name="audience" />
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="sticky top-4 space-y-5">
                <div class="rounded-mci bg-primary/10 p-4 text-primary">
                    <div class="text-3xl font-semibold tabular-nums" wire:loading.class="opacity-50">{{ $this->reach }}</div>
                    <div class="text-sm">{{ __('osób z wybranych grup, ról i list') }}</div>
                    @if ($this->mentionsInBody)
                        <div class="mt-1 text-xs opacity-80">{{ trans_choice('+ :count wzmianka w treści|+ :count wzmianki w treści|+ :count wzmianek w treści', $this->mentionsInBody) }}</div>
                    @endif
                </div>

                <div class="space-y-2">
                    <flux:label>{{ __('Publikacja') }}</flux:label>
                    <div class="flex gap-2">
                        <x-ui.pill wire:click="$set('when', 'now')" :active="$when === 'now'">{{ __('Teraz') }}</x-ui.pill>
                        <x-ui.pill wire:click="$set('when', 'later')" :active="$when === 'later'"><flux:icon name="clock" variant="micro" /> {{ __('Zaplanuj') }}</x-ui.pill>
                    </div>
                    @if ($when === 'later')
                        <flux:input type="datetime-local" wire:model="publish_at" />
                    @endif
                </div>

                <div class="space-y-2">
                    <flux:label>{{ __('Wygasa') }}</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['' => __('Nigdy'), '1' => __('Po 1 dniu'), '7' => __('Po 7 dniach'), '30' => __('Po 30 dniach')] as $value => $label)
                            <x-ui.pill wire:click="$set('expiry', '{{ $value }}')" :active="$expiry === (string) $value">{{ $label }}</x-ui.pill>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-3">
                    <flux:switch wire:model.live="requires_confirmation" :label="__('Wymagaj potwierdzenia zapoznania się')" />
                    <flux:switch wire:model.live="pinned" :label="__('Przypnij na górze listy')" />
                    @if ($this->canPublishPublic)
                        <flux:switch wire:model.live="is_public" :label="__('Pokaż także na stronie publicznej')" />
                    @endif
                </div>

                <flux:button type="submit" variant="primary" icon="paper-airplane" class="w-full">
                    {{ $when === 'later' ? __('Zaplanuj komunikat') : __('Wyślij komunikat') }}
                </flux:button>
            </flux:card>

            @if ($is_public)
                <flux:card class="space-y-2">
                    <div class="flex items-center gap-2 text-sm font-medium text-zinc-500"><flux:icon name="eye" variant="micro" /> {{ __('Podgląd publiczny') }}</div>
                    <flux:heading>{{ $title ?: __('(bez tytułu)') }}</flux:heading>
                    <div class="mci-prose text-sm text-zinc-700 dark:text-zinc-200">{!! $publicPreview !!}</div>
                    <flux:text size="sm">{{ __('Na stronie publicznej osoby są ukryte jako „@…”, chyba że same udostępniły profil publicznie.') }}</flux:text>
                </flux:card>
            @endif
        </div>
    </form>
</div>
