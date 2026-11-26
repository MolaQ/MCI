@php($p = $this->project)
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:button :href="route('projects.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">{{ __('Projekty') }}</flux:button>
        <div class="flex flex-wrap gap-2">
            @if ($canEdit && $inOwnerSchool)
                <flux:button :href="route('projects.edit', $p)" wire:navigate size="sm" icon="pencil-square">{{ __('Edytuj') }}</flux:button>
            @endif
            @if ($canDelete && $inOwnerSchool)
                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete" wire:confirm="{{ __('Usunąć projekt? Powiązane zadania i wnioski zostaną, ale bez projektu.') }}">{{ __('Usuń') }}</flux:button>
            @endif
        </div>
    </div>

    {{-- Header: cover, name, status, progress --}}
    <section class="overflow-hidden rounded-mci border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        @if ($p->cover)
            <img src="{{ $p->cover->url() }}" alt="" class="aspect-[4/1] max-h-64 w-full object-cover" />
        @endif
        <div class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-end">
            <div class="min-w-0 space-y-3">
                <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                    <flux:badge size="sm" :icon="$p->kind->icon()">{{ $p->kind->label() }}</flux:badge>
                    <span class="inline-flex items-center gap-1"><x-app.school-badge :school="$p->school" size="sm" /> {{ $p->school->short_name }}</span>
                    @foreach ($p->partnerSchools as $ps)
                        <span class="inline-flex items-center gap-1"><flux:icon name="arrows-right-left" variant="micro" /> {{ $ps->short_name }}</span>
                    @endforeach
                    @if ($p->visibility === 'team')
                        <span class="inline-flex items-center gap-1"><flux:icon name="lock-closed" variant="micro" /> {{ __('tylko zespół') }}</span>
                    @endif
                </div>
                <flux:heading size="xl" level="1">{{ $p->name }}</flux:heading>
                <div class="flex flex-wrap gap-2" role="toolbar" aria-label="{{ __('Status projektu') }}">
                    @if ($canEdit)
                        @foreach ($statuses as $s)
                            <x-ui.pill wire:click="setStatus('{{ $s->value }}')" :active="$p->status === $s" data-project-status="{{ $s->value }}"><flux:icon :name="$s->icon()" variant="micro" /> {{ $s->label() }}</x-ui.pill>
                        @endforeach
                    @else
                        <flux:badge :color="$p->status->color()" :icon="$p->status->icon()">{{ $p->status->label() }}</flux:badge>
                    @endif
                </div>
            </div>
            <div class="space-y-2" data-project-progress>
                <div class="flex items-baseline justify-between">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Postęp') }}</span>
                    <span class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $progress['percent'] }}%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $progress['percent'] }}%"></div>
                </div>
                <div class="flex justify-between text-xs text-zinc-500">
                    <span class="inline-flex items-center gap-1"><flux:icon name="flag" variant="micro" /> {{ __('Kamienie milowe: :done/:total', $progress['milestones']) }}</span>
                    <span class="inline-flex items-center gap-1"><flux:icon name="check-circle" variant="micro" /> {{ __('Zadania: :done/:total', $progress['tasks']) }}</span>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3 2xl:grid-cols-4">
        <div class="min-w-0 space-y-6 lg:col-span-2 2xl:col-span-3">
            {{-- Milestones --}}
            <flux:card class="space-y-4" data-milestones>
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Kamienie milowe') }}</flux:heading>
                </div>
                @if ($p->milestones->isNotEmpty())
                    <ol class="relative space-y-1 border-s border-zinc-200 ps-5 dark:border-zinc-700">
                        @foreach ($p->milestones as $i => $m)
                            <li wire:key="m-{{ $m->id }}" class="group relative flex items-center gap-3 rounded-mci py-1.5 pe-2">
                                @php($dot = ['absolute -start-[1.9rem] flex size-5 items-center justify-center rounded-full border-2 bg-white dark:bg-zinc-900',
                                    'border-emerald-500 bg-emerald-500! text-white' => $m->isDone(), 'border-red-500' => $m->isOverdue(), 'border-zinc-300 dark:border-zinc-600' => ! $m->isDone() && ! $m->isOverdue()])
                                @if ($canWork)
                                    <button type="button" wire:click="toggleMilestone({{ $m->id }})" role="checkbox" aria-checked="{{ $m->isDone() ? 'true' : 'false' }}" aria-label="{{ __('Osiągnięty: :title', ['title' => $m->title]) }}"
                                            @class(array_merge($dot, ['transition hover:border-emerald-500']))>
                                        @if ($m->isDone())<flux:icon name="check" variant="micro" class="size-3" />@endif
                                    </button>
                                @else
                                    <span @class($dot)>@if ($m->isDone())<flux:icon name="check" variant="micro" class="size-3" />@endif</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div @class(['text-sm font-medium break-words', 'text-zinc-400 line-through' => $m->isDone()])>{{ $m->title }}</div>
                                    @if ($m->isDone())
                                        <div class="text-xs text-zinc-500">{{ __('Osiągnięty :date', ['date' => $m->completed_at->translatedFormat('j M')]) }}{{ $m->completer ? ' · '.$m->completer->name : '' }}</div>
                                    @endif
                                </div>
                                @if ($m->due_at)
                                    <span @class(['shrink-0 text-xs', 'font-semibold text-red-600' => $m->isOverdue(), 'text-zinc-500' => ! $m->isOverdue()])>{{ $m->due_at->format('H:i') === '23:59' ? $m->due_at->translatedFormat('j M Y') : $m->due_at->translatedFormat('j M Y, H:i') }}</span>
                                @endif
                                @if ($canEdit)
                                    <div class="flex shrink-0 items-center opacity-60 group-hover:opacity-100">
                                        @if ($i > 0)<button type="button" wire:click="moveMilestone({{ $m->id }}, -1)" class="rounded p-1 text-zinc-400 hover:text-zinc-700" title="{{ __('Wyżej') }}"><flux:icon.chevron-up variant="micro" /></button>@endif
                                        @if ($i < $p->milestones->count() - 1)<button type="button" wire:click="moveMilestone({{ $m->id }}, 1)" class="rounded p-1 text-zinc-400 hover:text-zinc-700" title="{{ __('Niżej') }}"><flux:icon.chevron-down variant="micro" /></button>@endif
                                        <button type="button" wire:click="editMilestone({{ $m->id }})" class="rounded p-1 text-zinc-400 hover:text-zinc-700" title="{{ __('Edytuj') }}"><flux:icon.pencil variant="micro" /></button>
                                        <button type="button" wire:click="deleteMilestone({{ $m->id }})" wire:confirm="{{ __('Usunąć kamień milowy?') }}" class="rounded p-1 text-zinc-400 hover:text-red-600" title="{{ __('Usuń') }}"><flux:icon.x-mark variant="micro" /></button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <flux:text>{{ __('Brak kamieni milowych. Podziel projekt na etapy z terminami.') }}</flux:text>
                @endif

                @if ($canEdit)
                    <form wire:submit="saveMilestone" class="grid gap-2 rounded-mci border border-dashed border-zinc-300 p-3 sm:grid-cols-[minmax(0,1fr)_10rem_7rem_auto] dark:border-zinc-600" data-milestone-form>
                        <flux:input wire:model="milestoneTitle" size="sm" :placeholder="$editingMilestone ? __('Nazwa kamienia milowego') : __('Nowy kamień milowy, np. Zamówienie nagród')" maxlength="200" />
                        <flux:input type="date" wire:model="milestoneDate" size="sm" :aria-label="__('Termin')" />
                        <flux:input type="time" wire:model="milestoneTime" size="sm" :aria-label="__('Godzina')" />
                        <div class="flex gap-1">
                            <flux:button type="submit" size="sm" variant="primary" :icon="$editingMilestone ? 'check' : 'plus'">{{ $editingMilestone ? __('Zapisz') : __('Dodaj') }}</flux:button>
                            @if ($editingMilestone)<flux:button size="sm" variant="ghost" wire:click="cancelMilestone">{{ __('Anuluj') }}</flux:button>@endif
                        </div>
                        <div class="sm:col-span-4"><flux:error name="milestoneTitle" /><flux:error name="milestoneDate" /><flux:error name="milestoneTime" /></div>
                    </form>
                @endif
            </flux:card>

            {{-- Linked To-Do tasks --}}
            @if ($tasksOn)
                <flux:card class="space-y-3" data-project-tasks>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:heading size="lg">{{ __('Zadania') }}</flux:heading>
                        @if ($canWork && $inOwnerSchool)
                            <flux:button :href="route('tasks.create', ['projekt' => $p->id])" wire:navigate size="sm" icon="plus">{{ __('Nowe zadanie w projekcie') }}</flux:button>
                        @endif
                    </div>
                    @forelse ($this->tasks as $t)
                        <div wire:key="t-{{ $t->id }}" class="flex items-center gap-3 rounded-mci px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <flux:icon :name="$t->status->icon()" variant="mini" @class(['shrink-0', 'text-emerald-500' => $t->status === \App\Enums\TaskStatus::Done, 'text-sky-500' => $t->status === \App\Enums\TaskStatus::InProgress, 'text-zinc-400' => ! in_array($t->status, [\App\Enums\TaskStatus::Done, \App\Enums\TaskStatus::InProgress], true)]) />
                            <a href="{{ route('tasks.show', $t) }}" wire:navigate class="min-w-0 flex-1">
                                <span @class(['block truncate text-sm', 'text-zinc-400 line-through' => ! $t->status->isOpen()])>{{ $t->title }}</span>
                                @if ($t->milestone || $t->school_id !== $p->school_id)
                                    <span class="block truncate text-xs text-zinc-500">{{ collect([$t->milestone?->title, $t->school_id !== $p->school_id ? $t->school?->short_name : null])->filter()->join(' · ') }}</span>
                                @endif
                            </a>
                            @if ($t->due_at)
                                <span @class(['shrink-0 text-xs', 'font-semibold text-red-600' => $t->isOverdue(), 'text-zinc-500' => ! $t->isOverdue()])>{{ $t->dueLabel() }}</span>
                            @endif
                            @if ($canWork)
                                <button type="button" wire:click="unlinkTask({{ $t->id }})" class="rounded p-1 text-zinc-400 hover:text-red-600" title="{{ __('Odłącz od projektu') }}"><flux:icon.link-slash variant="micro" /></button>
                            @endif
                        </div>
                    @empty
                        <flux:text>{{ __('Brak powiązanych zadań.') }}</flux:text>
                    @endforelse
                    @if ($canWork)
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_14rem]">
                            <div class="relative">
                                <flux:input wire:model.live.debounce.250ms="taskSearch" size="sm" icon="link" :placeholder="__('Powiąż istniejące zadanie — wpisz tytuł')" autocomplete="off" />
                                @if ($this->taskResults->isNotEmpty())
                                    <div class="mci-fade-up absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-mci border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
                                        @foreach ($this->taskResults as $r)
                                            <button type="button" wire:key="tr-{{ $r->id }}" wire:click="linkTask({{ $r->id }})" class="flex w-full items-center gap-3 px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                                <flux:icon :name="$r->status->icon()" variant="micro" class="text-zinc-400" /> <span class="flex-1 truncate">{{ $r->title }}</span> <flux:icon name="link" variant="micro" class="text-zinc-400" />
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            @if ($p->milestones->isNotEmpty())
                                <flux:select wire:model="linkMilestone" size="sm" :aria-label="__('Kamień milowy')">
                                    <flux:select.option value="">{{ __('Bez kamienia milowego') }}</flux:select.option>
                                    @foreach ($p->milestones as $m)
                                        <flux:select.option :value="$m->id">{{ $m->title }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                        </div>
                        <flux:error name="link" />
                    @endif
                </flux:card>
            @endif

            {{-- Linked workflow requests --}}
            @if ($workflowsOn)
                <flux:card class="space-y-3" data-project-requests>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:heading size="lg">{{ __('Wnioski i obiegi') }}</flux:heading>
                        @if ($canWork && $inOwnerSchool)
                            <flux:button :href="route('workflows.create', ['projekt' => $p->id])" wire:navigate size="sm" icon="plus">{{ __('Nowy wniosek w projekcie') }}</flux:button>
                        @endif
                    </div>
                    @forelse ($this->requests as $r)
                        <div wire:key="r-{{ $r->id }}" class="flex items-center gap-3 rounded-mci px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <flux:icon :name="$r->status->icon()" variant="mini" class="shrink-0 text-zinc-400" />
                            <a href="{{ route('workflows.show', $r) }}" wire:navigate class="min-w-0 flex-1">
                                <span class="block truncate text-sm">{{ $r->title }}</span>
                                <span class="block truncate text-xs text-zinc-500">{{ $r->template_name }} · {{ $r->requester?->name }}</span>
                            </a>
                            <flux:badge size="sm" :color="$r->status->color()">{{ $r->status->label() }}</flux:badge>
                            @if ($canWork)
                                <button type="button" wire:click="unlinkRequest({{ $r->id }})" class="rounded p-1 text-zinc-400 hover:text-red-600" title="{{ __('Odłącz od projektu') }}"><flux:icon.link-slash variant="micro" /></button>
                            @endif
                        </div>
                    @empty
                        <flux:text>{{ __('Brak powiązanych wniosków.') }}</flux:text>
                    @endforelse
                    @if ($canWork)
                        <div class="relative">
                            <flux:input wire:model.live.debounce.250ms="requestSearch" size="sm" icon="link" :placeholder="__('Powiąż istniejący wniosek — wpisz tytuł')" autocomplete="off" />
                            @if ($this->requestResults->isNotEmpty())
                                <div class="mci-fade-up absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-mci border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
                                    @foreach ($this->requestResults as $r)
                                        <button type="button" wire:key="rr-{{ $r->id }}" wire:click="linkRequest({{ $r->id }})" class="flex w-full items-center gap-3 px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                            <flux:icon :name="$r->status->icon()" variant="micro" class="text-zinc-400" /> <span class="flex-1 truncate">{{ $r->title }}</span> <span class="text-xs text-zinc-400">{{ $r->template_name }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </flux:card>
            @endif

            @if ($p->description)
                <flux:card class="space-y-2">
                    <flux:heading size="lg">{{ __('Opis') }}</flux:heading>
                    <div class="mci-prose max-w-4xl">{!! $p->description !!}</div>
                </flux:card>
            @endif
        </div>

        <aside class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Szczegóły') }}</flux:heading>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Koordynator') }}</dt>
                        <dd class="mt-0.5 flex items-center gap-2">@if ($p->coordinator)<flux:avatar size="xs" :initials="$p->coordinator->initials()" /> {{ $p->coordinator->name }}@else — @endif</dd>
                    </div>
                    @if ($p->datesLabel())
                        <div><dt class="text-xs text-zinc-500">{{ __('Termin') }}</dt><dd class="mt-0.5">{{ $p->datesLabel() }}</dd></div>
                    @endif
                    @if ($p->budget_notes)
                        <div><dt class="text-xs text-zinc-500">{{ __('Budżet i zasoby') }}</dt><dd class="mt-0.5 whitespace-pre-line">{{ $p->budget_notes }}</dd></div>
                    @endif
                    <div><dt class="text-xs text-zinc-500">{{ __('Założony') }}</dt><dd class="mt-0.5">{{ $p->created_at->translatedFormat('j M Y') }}{{ $p->creator ? ' · '.$p->creator->name : '' }}</dd></div>
                </dl>
            </flux:card>

            <flux:card class="space-y-3" data-project-members>
                <flux:heading size="lg">{{ __('Zespół') }}</flux:heading>
                @forelse ($p->members as $member)
                    <div class="flex items-center gap-2 text-sm">
                        <flux:icon :name="$member->icon()" variant="micro" class="text-zinc-400" />
                        <span class="min-w-0 flex-1 truncate">{{ $member->label() }}</span>
                        @if ($member->school_id !== $p->school_id)<span class="text-xs text-zinc-400">{{ $member->school?->short_name }}</span>@endif
                    </div>
                @empty
                    <flux:text>{{ __('Na razie tylko koordynator.') }}</flux:text>
                @endforelse
            </flux:card>
        </aside>
    </div>
</div>
