<div class="space-y-6" wire:poll.60s.visible>
    <x-ui.page-header :title="__('Zadania')" :subtitle="__('Twoja lista: zadania osobiste, przydzielone Tobie, Twoim grupom i Twojej roli.')">
        <x-slot:actions>
            <flux:button :href="route('tasks.create')" wire:navigate variant="primary" icon="plus">{{ __('Nowe zadanie') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="quickAdd" class="flex flex-col gap-2 sm:flex-row sm:items-start">
        <div class="flex-1">
            <flux:input wire:model="quickTitle" icon="plus-circle" :placeholder="__('Szybko dodaj zadanie dla siebie i naciśnij Enter…')" maxlength="200" :aria-label="__('Nowe zadanie osobiste')" />
            <flux:error name="quickTitle" />
            <flux:error name="title" />
        </div>
        <div class="flex gap-2">
            @foreach (['' => __('Bez terminu'), 'today' => __('Dziś'), 'tomorrow' => __('Jutro')] as $value => $label)
                <x-ui.pill wire:click="$set('quickDue', '{{ $value }}')" :active="$quickDue === $value" class="py-2">{{ $label }}</x-ui.pill>
            @endforeach
            <flux:button type="submit" variant="filled" icon="arrow-turn-down-left" :aria-label="__('Dodaj')" class="sm:hidden" />
        </div>
    </form>

    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="{{ __('Filtry zadań') }}">
            <x-ui.pill wire:click="setFilter('view', 'mine')" :active="$view === 'mine'" :count="$this->counts['mine']"><flux:icon name="user" variant="micro" /> {{ __('Moje zadania') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('view', 'assigned_by')" :active="$view === 'assigned_by'" :count="$this->counts['assigned_by']"><flux:icon name="paper-airplane" variant="micro" /> {{ __('Zlecone przeze mnie') }}</x-ui.pill>
            <span class="mx-1 hidden h-5 w-px bg-zinc-200 sm:block dark:bg-zinc-700"></span>
            <x-ui.pill wire:click="setFilter('when', '{{ $when === 'overdue' ? 'all' : 'overdue' }}')" :active="$when === 'overdue'" :count="$this->counts['overdue']"
                :class="\Illuminate\Support\Arr::toCssClasses(['!border-red-300 !text-red-700 dark:!border-red-500/50 dark:!text-red-300' => $when !== 'overdue' && $this->counts['overdue'] > 0, '!border-red-600 !bg-red-600' => $when === 'overdue'])">
                <flux:icon name="exclamation-circle" variant="micro" /> {{ __('Zaległe') }}
            </x-ui.pill>
            <x-ui.pill wire:click="setFilter('when', '{{ $when === 'today' ? 'all' : 'today' }}')" :active="$when === 'today'" :count="$this->counts['today']"><flux:icon name="sun" variant="micro" /> {{ __('Dziś') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('when', '{{ $when === 'week' ? 'all' : 'week' }}')" :active="$when === 'week'" :count="$this->counts['week']"><flux:icon name="calendar-days" variant="micro" /> {{ __('Ten tydzień') }}</x-ui.pill>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($this->groups->isNotEmpty())
                <flux:dropdown>
                    <x-ui.pill :active="$group !== ''">
                        <flux:icon name="user-group" variant="micro" />
                        {{ $group !== '' ? ($this->groups->firstWhere('id', (int) $group)?->name ?? __('Grupa')) : __('Każda grupa') }}
                        <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                    </x-ui.pill>
                    <flux:menu>
                        <flux:menu.item wire:click="setFilter('group', '')">{{ __('Każda grupa') }}</flux:menu.item>
                        <flux:menu.separator />
                        @foreach ($this->groups as $g)
                            <flux:menu.item wire:click="setFilter('group', '{{ $g->id }}')">
                                <span class="me-2 inline-block size-2 rounded-full" style="background: {{ $g->color ?: '#a1a1aa' }}"></span>{{ $g->name }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endif

            <flux:dropdown>
                <x-ui.pill :active="$priority !== ''">
                    <flux:icon name="flag" variant="micro" />
                    {{ $priority !== '' ? \App\Enums\TaskPriority::from((int) $priority)->label() : __('Każdy priorytet') }}
                    <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                </x-ui.pill>
                <flux:menu>
                    <flux:menu.item wire:click="setFilter('priority', '')">{{ __('Każdy priorytet') }}</flux:menu.item>
                    <flux:menu.separator />
                    @foreach ($priorities as $p)
                        <flux:menu.item :icon="$p->icon()" wire:click="setFilter('priority', '{{ $p->value }}')">{{ $p->label() }}</flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>

            @if ($this->multiSchool)
                <x-ui.pill wire:click="$toggle('allSchools')" :active="$allSchools"><flux:icon name="building-library" variant="micro" /> {{ __('Wszystkie moje szkoły') }}</x-ui.pill>
            @endif

            <x-ui.pill wire:click="$toggle('showDone')" :active="$showDone"><flux:icon name="check-circle" variant="micro" /> {{ __('Pokaż zakończone') }}</x-ui.pill>

            @if ($filtered)
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="resetFilters">{{ __('Wyczyść filtry') }}</flux:button>
            @endif
        </div>
    </div>

    <flux:card class="overflow-hidden !p-0">
        <div class="divide-y divide-zinc-100 dark:divide-zinc-700/70" wire:loading.class="opacity-60" wire:target="setFilter,cycle,setStatus,allSchools,showDone,resetFilters">
            @forelse ($this->tasks as $task)
                <x-app.task-row :task="$task" :statuses="$statuses" :show-school="$allSchools" wire:key="t-{{ $task->id }}" />
            @empty
                <x-ui.empty-state icon="check-circle"
                    :title="$filtered ? __('Nic nie pasuje do filtrów') : ($view === 'mine' ? __('Wszystko zrobione') : __('Nie zleciłeś(-aś) jeszcze zadań'))"
                    :description="$filtered ? __('Zmień albo wyczyść filtry.') : ($view === 'mine' ? __('Dodaj zadanie powyżej albo poczekaj, aż ktoś Ci je przydzieli.') : __('Utwórz zadanie i przydziel je osobie, grupie lub roli.'))" />
            @endforelse
        </div>
    </flux:card>

    {{ $this->tasks->links() }}
</div>
