<div class="space-y-6">
    <x-ui.page-header :title="$taskId ? __('Edycja zadania') : __('Nowe zadanie')" :subtitle="__('Bez wybranych osób zadanie jest osobiste i widzisz je tylko Ty.')">
        <x-slot:actions>
            <flux:button :href="$taskId ? route('tasks.show', $taskId) : route('tasks.index')" wire:navigate variant="ghost" icon="arrow-left">{{ $taskId ? __('Zadanie') : __('Zadania') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($this->linkProject)
        <div class="flex items-center gap-2 rounded-mci border border-primary/30 bg-primary/5 px-4 py-2.5 text-sm" data-link-project>
            <flux:icon name="rectangle-stack" variant="mini" class="text-primary" />
            {{ __('Zadanie trafi do projektu :name. Przydziel je komuś — zadania osobiste nie należą do projektów.', ['name' => $this->linkProject->name]) }}
        </div>
    @endif

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <flux:input wire:model="title" :label="__('Tytuł')" :placeholder="__('np. Przygotować salę na dzień otwarty')" maxlength="200" required />

                <x-ui.editor property="description" :label="__('Opis')" :placeholder="__('Szczegóły zadania… Wpisz @, aby wspomnieć osobę lub grupę.')" />

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <flux:label>{{ __('Lista kontrolna') }}</flux:label>
                        @if ($checklist)
                            <span class="text-xs tabular-nums text-zinc-500">{{ collect($checklist)->where('is_done', true)->count() }}/{{ count($checklist) }}</span>
                        @endif
                    </div>
                    @if ($checklist)
                        <ul class="divide-y divide-zinc-100 rounded-mci border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @foreach ($checklist as $i => $item)
                                <li wire:key="cl-{{ $i }}-{{ $item['id'] ?? 'n' }}" class="flex items-center gap-2 px-2 py-1.5">
                                    <input type="checkbox" wire:model="checklist.{{ $i }}.is_done" class="size-4 rounded border-zinc-300 text-emerald-600 dark:border-zinc-600 dark:bg-zinc-800" aria-label="{{ __('Zrobione') }}" />
                                    <input type="text" wire:model="checklist.{{ $i }}.body" maxlength="200" class="min-w-0 flex-1 border-0 bg-transparent p-1 text-sm focus:ring-0 dark:text-zinc-100" aria-label="{{ __('Punkt :n', ['n' => $i + 1]) }}" />
                                    @if ($i > 0)
                                        <button type="button" wire:click="moveItem({{ $i }}, -1)" class="rounded p-1 text-zinc-400 hover:text-zinc-700" title="{{ __('Wyżej') }}"><flux:icon.chevron-up variant="micro" /></button>
                                    @endif
                                    @if ($i < count($checklist) - 1)
                                        <button type="button" wire:click="moveItem({{ $i }}, 1)" class="rounded p-1 text-zinc-400 hover:text-zinc-700" title="{{ __('Niżej') }}"><flux:icon.chevron-down variant="micro" /></button>
                                    @endif
                                    <button type="button" wire:click="removeItem({{ $i }})" class="rounded p-1 text-zinc-400 hover:text-red-600" title="{{ __('Usuń') }}"><flux:icon.x-mark variant="micro" /></button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="flex gap-2">
                        <flux:input wire:model="newItem" wire:keydown.enter.prevent="addItem" size="sm" icon="plus" :placeholder="__('Dodaj punkt i naciśnij Enter')" maxlength="200" class="flex-1" />
                        <flux:button size="sm" wire:click="addItem">{{ __('Dodaj') }}</flux:button>
                    </div>
                    <flux:error name="checklist" />
                </div>

                @if ($attachments)
                    @php($stored = $this->task?->images->whereNotIn('id', $removeImageIds) ?? collect())
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <flux:label>{{ __('Obrazy') }} <span class="font-normal text-zinc-400">({{ $stored->count() + count($images) }}/{{ config('mci.media.max_post_images') }})</span></flux:label>
                            @if ($stored->count() + count($images) < config('mci.media.max_post_images'))
                                <x-ui.image-editor model="newImage" gallery :presets="['free', '16:9', '4:3', '1:1']" :max-size="2000" :background="false" />
                            @endif
                        </div>
                        @if ($stored->isNotEmpty() || $images)
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                @foreach ($stored as $media)
                                    <div wire:key="sm-{{ $media->id }}" class="relative overflow-hidden rounded-mci border border-zinc-200 dark:border-zinc-700">
                                        <img src="{{ $media->url() }}" alt="" class="aspect-video w-full object-cover" />
                                        <button type="button" wire:click="removeStoredImage({{ $media->id }})" class="absolute end-1.5 bottom-1.5 rounded bg-white/90 p-1 text-red-600" title="{{ __('Usuń') }}"><flux:icon.trash variant="micro" /></button>
                                    </div>
                                @endforeach
                                @foreach ($images as $i => $image)
                                    <div wire:key="ni-{{ $i }}-{{ $image->getFilename() }}" class="relative overflow-hidden rounded-mci border border-zinc-200 dark:border-zinc-700">
                                        <img src="{{ $image->temporaryUrl() }}" alt="" class="aspect-video w-full object-cover" />
                                        <button type="button" wire:click="removeNewImage({{ $i }})" class="absolute end-1.5 bottom-1.5 rounded bg-white/90 p-1 text-red-600" title="{{ __('Usuń') }}"><flux:icon.trash variant="micro" /></button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <flux:error name="newImage" />
                        <flux:error name="images" />
                    </div>
                @endif
            </flux:card>

            <flux:card class="space-y-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Dla kogo') }}</flux:heading>
                        <flux:text>{{ $this->canAssignOthers ? __('Osoby, grupy lub role. Zadanie grupy lub roli wystarczy wykonać raz.') : __('Możesz tworzyć zadania dla siebie. Przydzielać innym mogą liderzy grup i koordynatorzy.') }}</flux:text>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-ui.pill wire:click="onlyMe" :active="$assignees === []"><flux:icon name="lock-closed" variant="micro" /> {{ __('Tylko dla mnie') }}</x-ui.pill>
                </div>

                @if ($this->canAssignOthers)
                    @if ($this->groups->isNotEmpty())
                        <div class="space-y-2">
                            <flux:label>{{ __('Grupy i zespoły') }}</flux:label>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->groups as $group)
                                    <x-ui.pill wire:key="ag-{{ $group->id }}" wire:click="toggleAssignee('group:{{ $group->id }}')" :active="in_array('group:'.$group->id, $assignees)">
                                        <span class="size-2 rounded-full" style="background: {{ $group->color ?: '#a1a1aa' }}"></span> {{ $group->name }}
                                    </x-ui.pill>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($this->canAssignRoles)
                        <div class="space-y-2">
                            <flux:label>{{ __('Role w szkole') }}</flux:label>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($roles as $role)
                                    <x-ui.pill wire:click="toggleAssignee('role:{{ $role->value }}')" :active="in_array('role:'.$role->value, $assignees)">
                                        <flux:icon name="identification" variant="micro" /> {{ $role->label() }}
                                    </x-ui.pill>
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
                                        <button type="button" wire:click="toggleAssignee('user:{{ $person->id }}')" aria-label="{{ __('Usuń :name', ['name' => $person->name]) }}">
                                            <flux:icon name="x-mark" variant="micro" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
                <flux:error name="assignees" />
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="sticky top-4 space-y-5">
                <div class="space-y-2">
                    <flux:label>{{ __('Priorytet') }}</flux:label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($priorities as $p)
                            <x-ui.pill wire:click="$set('priority', {{ $p->value }})" :active="$priority === $p->value" class="justify-center">
                                <flux:icon :name="$p->icon()" variant="micro" /> {{ $p->label() }}
                            </x-ui.pill>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-2">
                    <flux:label>{{ __('Termin') }}</flux:label>
                    <div class="grid grid-cols-5 gap-2">
                        <flux:input type="date" wire:model.live="due_date" class="col-span-3" :aria-label="__('Dzień')" />
                        <flux:input type="time" wire:model="due_time" class="col-span-2" :aria-label="__('Godzina')" :disabled="$due_date === ''" />
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['today' => [__('Dziś'), now()], 'tomorrow' => [__('Jutro'), now()->addDay()], 'friday' => [__('Piątek'), now()->next(\Carbon\CarbonInterface::FRIDAY)], 'week' => [__('Za tydzień'), now()->addWeek()]] as $key => [$label, $date])
                            <x-ui.pill wire:click="$set('due_date', '{{ $date->format('Y-m-d') }}')" :active="$due_date === $date->format('Y-m-d')" class="!px-2 !py-0.5 !text-xs">{{ $label }}</x-ui.pill>
                        @endforeach
                        @if ($due_date !== '')
                            <x-ui.pill wire:click="clearDue" class="!px-2 !py-0.5 !text-xs"><flux:icon name="x-mark" variant="micro" /> {{ __('Bez terminu') }}</x-ui.pill>
                        @endif
                    </div>
                    <flux:text size="sm">{{ __('Czas polski. Bez godziny termin mija o końcu dnia.') }}</flux:text>
                    <flux:error name="due_date" />
                    <flux:error name="due_time" />
                </div>

                @if ($reminders && $due_date !== '')
                    <flux:select wire:model="remind" :label="__('Przypomnienie')">
                        <flux:select.option value="">{{ __('Domyślne') }}</flux:select.option>
                        <flux:select.option value="0">{{ __('W chwili terminu') }}</flux:select.option>
                        <flux:select.option value="15">{{ __('15 minut przed') }}</flux:select.option>
                        <flux:select.option value="60">{{ __('1 godzinę przed') }}</flux:select.option>
                        <flux:select.option value="180">{{ __('3 godziny przed') }}</flux:select.option>
                        <flux:select.option value="1440">{{ __('1 dzień przed') }}</flux:select.option>
                        <flux:select.option value="2880">{{ __('2 dni przed') }}</flux:select.option>
                    </flux:select>
                @endif

                @if ($errors->any())
                    <div role="alert" class="space-y-1 rounded-mci border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                        <p class="flex items-center gap-1.5 font-medium"><flux:icon name="exclamation-triangle" variant="micro" /> {{ __('Zadanie nie zostało zapisane:') }}</p>
                        <ul class="list-disc space-y-0.5 ps-5">
                            @foreach (array_unique($errors->all()) as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <flux:button type="submit" variant="primary" icon="check" class="w-full">
                    {{ $taskId ? __('Zapisz zmiany') : ($assignees ? __('Utwórz i przydziel') : __('Dodaj zadanie')) }}
                </flux:button>
            </flux:card>
        </div>
    </form>
</div>
