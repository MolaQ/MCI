<div class="space-y-6">
    <x-ui.page-header :title="$templateId ? __('Edycja szablonu') : __('Nowy szablon obiegu')"
        :subtitle="$scope === 'site' ? __('Szablon wspólny dla wszystkich szkół portalu.') : __('Szablon dla tej szkoły. Złożone wnioski zachowują swoje etapy, nawet gdy zmienisz szablon.')">
        <x-slot:actions>
            <flux:button :href="$scope === 'site' ? route('site.workflows') : route('admin.workflows')" wire:navigate variant="ghost" icon="arrow-left">{{ __('Szablony') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <flux:input wire:model="name" :label="__('Nazwa')" :placeholder="__('np. Zgoda na wycieczkę szkolną')" maxlength="120" required />
                <flux:textarea wire:model="description" :label="__('Opis dla składających')" rows="2" maxlength="1000" />
                <flux:field>
                    <flux:label>{{ __('Ikona') }}</flux:label>
                    <div class="grid grid-cols-8 gap-1.5 sm:grid-cols-12" role="radiogroup" aria-label="{{ __('Ikona') }}">
                        @foreach ($icons as $ic)
                            <button type="button" wire:click="$set('icon', '{{ $ic }}')" role="radio" aria-checked="{{ $icon === $ic ? 'true' : 'false' }}" title="{{ $ic }}"
                                @class(['flex aspect-square items-center justify-center rounded-mci border transition-colors',
                                    'border-primary bg-primary text-primary-foreground' => $icon === $ic,
                                    'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800' => $icon !== $ic])>
                                <flux:icon :name="$ic" variant="mini" />
                            </button>
                        @endforeach
                    </div>
                </flux:field>
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Etapy') }}</flux:heading>
                        <flux:text>{{ __('Kolejno: wniosek przechodzi dalej po zatwierdzeniu każdego etapu.') }}</flux:text>
                    </div>
                    <flux:button size="sm" icon="plus" wire:click="addStage" :disabled="count($stages) >= \App\Domain\Workflows\Actions\ManageTemplates::MAX_STAGES">{{ __('Etap') }}</flux:button>
                </div>
                <flux:error name="stages" />
                @foreach ($stages as $i => $stage)
                    @php($targetSchool = $stage['school_id'] !== '' ? (int) $stage['school_id'] : $homeSchoolId)
                    @php($opts = $this->optionsFor($targetSchool))
                    <div wire:key="st-{{ $i }}" class="space-y-3 rounded-mci border border-zinc-200 p-4 dark:border-zinc-700" data-stage="{{ $i }}">
                        <div class="flex items-center gap-2">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">{{ $i + 1 }}</span>
                            <flux:input wire:model="stages.{{ $i }}.name" :placeholder="__('Nazwa etapu, np. Akceptacja wicedyrektora')" maxlength="120" class="flex-1" :aria-label="__('Nazwa etapu')" />
                            <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="move('stages', {{ $i }}, -1)" :disabled="$i === 0" :aria-label="__('Wyżej')" />
                            <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="move('stages', {{ $i }}, 1)" :disabled="$loop->last" :aria-label="__('Niżej')" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="remove('stages', {{ $i }})" :aria-label="__('Usuń etap')" class="!text-red-600" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @if ($this->crossSchool || $scope === 'site')
                                <flux:select wire:model.live="stages.{{ $i }}.school_id" :label="__('Szkoła decydująca')">
                                    <flux:select.option value="">{{ $scope === 'site' ? __('Szkoła wnioskodawcy') : __('Ta szkoła') }}</flux:select.option>
                                    @if ($this->crossSchool)
                                        @foreach ($this->schools as $s)
                                            @continue($s->id === $homeSchoolId)
                                            <flux:select.option :value="$s->id">{{ $s->short_name }}</flux:select.option>
                                        @endforeach
                                    @endif
                                </flux:select>
                            @endif
                            <flux:select wire:model.live="stages.{{ $i }}.approver_type" :label="__('Kto decyduje')">
                                <flux:select.option value="role">{{ __('Rola') }}</flux:select.option>
                                @if ($targetSchool)
                                    <flux:select.option value="group">{{ __('Grupa') }}</flux:select.option>
                                    <flux:select.option value="user">{{ __('Osoba') }}</flux:select.option>
                                @endif
                            </flux:select>
                            <flux:select wire:model="stages.{{ $i }}.approver_id" :label="match ($stage['approver_type']) { 'group' => __('Grupa'), 'user' => __('Osoba'), default => __('Rola') }">
                                @if ($stage['approver_type'] === 'role')
                                    @foreach ($roles as $role)
                                        <flux:select.option :value="$role->value">{{ $role->label() }}</flux:select.option>
                                    @endforeach
                                @else
                                    <flux:select.option value="">{{ __('— wybierz —') }}</flux:select.option>
                                    @foreach ($stage['approver_type'] === 'group' ? $opts['groups'] : $opts['users'] as $o)
                                        <flux:select.option :value="(string) $o->id">{{ $o->name }}</flux:select.option>
                                    @endforeach
                                @endif
                            </flux:select>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <flux:input type="number" min="1" max="2160" wire:model="stages.{{ $i }}.sla_hours" :label="__('Czas na decyzję (godz.)')" :placeholder="__('bez terminu')" />
                            @if ($this->tasksAvailable)
                                <div class="sm:col-span-2 space-y-2 sm:pt-6">
                                    <flux:checkbox wire:model="stages.{{ $i }}.task_on_reach" :label="__('Utwórz zadanie To-Do dla decydujących')" />
                                </div>
                            @endif
                        </div>
                        @if ($this->tasksAvailable)
                            <flux:input wire:model="stages.{{ $i }}.followup_task" :label="__('Zadanie dla wnioskodawcy po zatwierdzeniu (opcjonalnie)')" :placeholder="__('np. Dostarczyć listę uczestników do sekretariatu')" maxlength="200" />
                        @endif
                        <flux:error name="stages.{{ $i }}" />
                    </div>
                @endforeach
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Pola wniosku') }}</flux:heading>
                        <flux:text>{{ __('Co wnioskodawca musi podać oprócz tytułu.') }}</flux:text>
                    </div>
                    <flux:button size="sm" icon="plus" wire:click="addField">{{ __('Pole') }}</flux:button>
                </div>
                <flux:error name="fields" />
                @forelse ($fields as $i => $field)
                    <div wire:key="fd-{{ $i }}" class="space-y-2 rounded-mci border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="grid gap-2 sm:grid-cols-[1fr_12rem_auto]">
                            <flux:input wire:model="fields.{{ $i }}.label" size="sm" :placeholder="__('Nazwa pola, np. Liczba uczniów')" maxlength="80" :aria-label="__('Nazwa pola')" />
                            <flux:select wire:model.live="fields.{{ $i }}.type" size="sm" :aria-label="__('Typ pola')">
                                @foreach ($types as $value => $label)
                                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <div class="flex items-center gap-1">
                                <flux:checkbox wire:model="fields.{{ $i }}.required" :label="__('wymagane')" />
                                <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="move('fields', {{ $i }}, -1)" :disabled="$i === 0" :aria-label="__('Wyżej')" />
                                <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="move('fields', {{ $i }}, 1)" :disabled="$loop->last" :aria-label="__('Niżej')" />
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="remove('fields', {{ $i }})" :aria-label="__('Usuń pole')" class="!text-red-600" />
                            </div>
                        </div>
                        @if ($field['type'] === 'select')
                            <flux:textarea wire:model="fields.{{ $i }}.options" rows="2" :placeholder="__('Opcje, każda w osobnej linii')" :aria-label="__('Opcje')" />
                        @endif
                        <flux:error name="fields.{{ $i }}" />
                    </div>
                @empty
                    <flux:text size="sm">{{ __('Brak dodatkowych pól — wystarczy tytuł i załączniki.') }}</flux:text>
                @endforelse
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-5 lg:sticky lg:top-4">
                <flux:select wire:model="min_attachments" :label="__('Wymagane załączniki')">
                    @foreach (range(0, config('mci.media.max_post_images')) as $n)
                        <flux:select.option :value="$n">{{ $n === 0 ? __('nie są wymagane') : trans_choice('co najmniej :count|co najmniej :count|co najmniej :count', $n) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:switch wire:model="is_active" :label="__('Dostępny do składania wniosków')" />

                @if ($errors->any())
                    <div role="alert" class="space-y-1 rounded-mci border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                        <p class="flex items-center gap-1.5 font-medium"><flux:icon name="exclamation-triangle" variant="micro" /> {{ __('Szablon nie został zapisany:') }}</p>
                        <ul class="list-disc space-y-0.5 ps-5">
                            @foreach (array_unique($errors->all()) as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <flux:button type="submit" variant="primary" icon="check" class="w-full">{{ __('Zapisz szablon') }}</flux:button>
            </flux:card>
        </div>
    </form>
</div>
