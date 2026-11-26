@php($t = $this->task)
@php($progress = $t->checklistProgress())
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:button :href="route('tasks.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">{{ __('Zadania') }}</flux:button>
        @if ($canEdit)
            <div class="flex gap-2">
                <flux:button :href="route('tasks.edit', $t)" wire:navigate size="sm" icon="pencil-square">{{ __('Edytuj') }}</flux:button>
                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete" wire:confirm="{{ __('Usunąć to zadanie?') }}" class="!text-red-600">{{ __('Usuń') }}</flux:button>
            </div>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <article class="space-y-5 lg:col-span-2">
            <flux:card class="space-y-5">
                <header class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$t->status->color()" :icon="$t->status->icon()">{{ $t->status->label() }}</flux:badge>
                        <flux:badge size="sm" :color="$t->priority->color()" :icon="$t->priority->icon()">{{ __('Priorytet: :p', ['p' => mb_strtolower($t->priority->label())]) }}</flux:badge>
                        @if ($t->is_personal)
                            <flux:badge size="sm" icon="lock-closed">{{ __('Osobiste') }}</flux:badge>
                        @endif
                        @if ($t->isOverdue())
                            <flux:badge size="sm" color="red" icon="exclamation-circle">{{ __('Po terminie') }}</flux:badge>
                        @endif
                    </div>
                    <flux:heading size="xl" level="1" @class(['line-through decoration-zinc-400' => $t->status === \App\Enums\TaskStatus::Cancelled])>{{ $t->title }}</flux:heading>
                </header>

                @if ($description)
                    <div class="mci-prose text-zinc-800 dark:text-zinc-100">{!! $description !!}</div>
                @endif

                <x-ui.gallery :images="$t->images" />

                @if ($t->checklist->isNotEmpty())
                    <section class="space-y-2" aria-labelledby="checklist-h">
                        <div class="flex items-center justify-between gap-3">
                            <flux:heading id="checklist-h">{{ __('Lista kontrolna') }}</flux:heading>
                            <span class="text-sm tabular-nums text-zinc-500">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 0 }}%"></div>
                        </div>
                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-700/70">
                            @foreach ($t->checklist as $item)
                                <li wire:key="ci-{{ $item->id }}">
                                    <label @class(['flex items-start gap-3 py-2', 'cursor-pointer' => $canWork])>
                                        <input type="checkbox" @checked($item->is_done) @disabled(! $canWork) wire:click="toggleItem({{ $item->id }})"
                                               class="mt-0.5 size-4 rounded border-zinc-300 text-emerald-600 focus:ring-primary dark:border-zinc-600 dark:bg-zinc-800" />
                                        <span @class(['text-sm', 'text-zinc-400 line-through' => $item->is_done, 'text-zinc-800 dark:text-zinc-100' => ! $item->is_done])>{{ $item->body }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </flux:card>
        </article>

        <aside class="space-y-6">
            @if ($canWork)
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('Status') }}</flux:heading>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($statuses as $status)
                            @continue($status === \App\Enums\TaskStatus::Cancelled && ! $canEdit)
                            <x-ui.pill wire:click="setStatus('{{ $status->value }}')" :active="$t->status === $status" class="justify-center py-2">
                                <flux:icon :name="$status->icon()" variant="micro" /> {{ $status->label() }}
                            </x-ui.pill>
                        @endforeach
                    </div>
                    @if ($t->status === \App\Enums\TaskStatus::Done && $t->completed_at)
                        <flux:text size="sm">{{ __('Wykonane :date przez :name.', ['date' => $t->completed_at->translatedFormat('j F, H:i'), 'name' => $t->completer?->name ?? __('—')]) }}</flux:text>
                    @endif
                </flux:card>
            @endif

            <flux:card>
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-zinc-500">{{ __('Termin') }}</dt>
                        <dd @class(['mt-0.5 font-medium', 'text-red-600 dark:text-red-400' => $t->isOverdue(), 'text-zinc-900 dark:text-white' => ! $t->isOverdue()])>
                            {{ $t->due_at ? ($t->all_day ? $t->due_at->translatedFormat('l, j F Y') : $t->due_at->translatedFormat('l, j F Y, H:i')) : __('bez terminu') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">{{ __('Dla kogo') }}</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            @forelse ($t->assignees as $a)
                                <flux:badge size="sm" :icon="$a->icon()">{{ $a->label() }}</flux:badge>
                            @empty
                                <span class="font-medium text-zinc-900 dark:text-white">{{ __('Zadanie osobiste') }}</span>
                            @endforelse
                        </dd>
                        @if ($t->assignees->contains(fn ($a) => $a->user_id === null))
                            <flux:text size="sm" class="mt-1">{{ __('Grupa lub rola: wystarczy, że zrobi to jedna osoba.') }}</flux:text>
                        @endif
                    </div>
                    <div>
                        <dt class="text-zinc-500">{{ __('Utworzone') }}</dt>
                        <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ $t->creator?->name ?? __('System') }} · {{ $t->created_at->translatedFormat('j F Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">{{ __('Szkoła') }}</dt>
                        <dd class="mt-0.5 inline-flex items-center gap-1.5 text-zinc-900 dark:text-white"><x-app.school-badge :school="$t->school" size="sm" /> {{ $t->school->short_name }}</dd>
                    </div>
                </dl>
            </flux:card>
        </aside>
    </div>
</div>
