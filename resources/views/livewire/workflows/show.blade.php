@php($r = $this->request)
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:button :href="route('workflows.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">{{ __('Obiegi') }}</flux:button>
        <div class="flex gap-2">
            @if ($canResubmit && ! $editing)
                <flux:button size="sm" variant="primary" icon="pencil-square" wire:click="startEdit">{{ __('Popraw i złóż ponownie') }}</flux:button>
            @endif
            @if ($canCancel)
                <flux:button size="sm" variant="ghost" icon="minus-circle" wire:click="cancel" wire:confirm="{{ __('Wycofać ten wniosek? Tej operacji nie można cofnąć.') }}">{{ __('Wycofaj') }}</flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3 2xl:grid-cols-4">
        <div class="min-w-0 space-y-6 lg:col-span-2 2xl:col-span-3">
            <flux:card class="space-y-5">
                <header class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$r->status->color()" :icon="$r->status->icon()">{{ $r->status->label() }}</flux:badge>
                        <flux:badge size="sm" :icon="$r->template?->icon ?? 'clipboard-document-check'">{{ $r->template_name }}</flux:badge>
                        @if ($r->isOverdue())
                            <flux:badge size="sm" color="red" icon="clock">{{ __('Po terminie decyzji') }}</flux:badge>
                        @endif
                    </div>
                    <flux:heading size="xl" level="1">{{ $r->title }}</flux:heading>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                        <span class="inline-flex items-center gap-2"><flux:avatar size="xs" :initials="$r->requester?->initials() ?? '?'" /> {{ $r->requester?->name ?? __('—') }}</span>
                        <span>{{ $r->submitted_at?->translatedFormat('j F Y, H:i') }}</span>
                        <span class="inline-flex items-center gap-1"><x-app.school-badge :school="$r->school" size="sm" /> {{ $r->school->short_name }}</span>
                    </div>
                </header>

                @if ($editing)
                    <form wire:submit="resubmit" class="space-y-4 rounded-mci border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-500/30 dark:bg-amber-500/5">
                        <flux:input wire:model="title" :label="__('Tytuł wniosku')" maxlength="200" />
                        @foreach ($fields as $field)
                            <div wire:key="ef-{{ $field['key'] }}"><x-app.workflow-field :field="$field" :model="'data.'.$field['key']" /></div>
                        @endforeach
                        <flux:textarea wire:model="comment" :label="__('Co poprawiono? (opcjonalnie)')" rows="2" />
                        <div class="flex justify-end gap-2">
                            <flux:button variant="ghost" wire:click="$set('editing', false)">{{ __('Anuluj') }}</flux:button>
                            <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Złóż ponownie') }}</flux:button>
                        </div>
                    </form>
                @elseif ($fields)
                    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ($fields as $field)
                            <div @class(['sm:col-span-2' => $field['type'] === 'textarea'])>
                                <dt class="text-xs text-zinc-500">{{ $field['label'] }}</dt>
                                <dd class="mt-0.5 whitespace-pre-line text-sm text-zinc-900 dark:text-zinc-100">{{ $display($field, $r->data[$field['key']] ?? null) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                <x-ui.gallery :images="$r->images" />
            </flux:card>

            @if ($canDecide)
                <flux:card class="space-y-4 border-sky-300 ring-2 ring-sky-500/10 dark:border-sky-500/40" data-decision-panel>
                    <div>
                        <flux:heading size="lg">{{ __('Twoja decyzja: :stage', ['stage' => $r->currentStage()['name'] ?? '']) }}</flux:heading>
                        <flux:text>{{ __('Komentarz jest wymagany przy odrzuceniu i zwrocie do poprawy.') }}</flux:text>
                    </div>
                    <x-ui.editor property="comment" :placeholder="__('Komentarz… Wpisz @, aby wspomnieć osobę.')" />
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.image-editor model="newImage" gallery :presets="['free', '4:3', '1:1']" :max-size="2000" :background="false" />
                        @foreach ($images as $i => $image)
                            <span wire:key="di-{{ $i }}-{{ $image->getFilename() }}" class="relative">
                                <img src="{{ $image->temporaryUrl() }}" alt="" class="size-12 rounded-mci object-cover" />
                                <button type="button" wire:click="removeImage({{ $i }})" class="absolute -end-1.5 -top-1.5 rounded-full bg-white p-0.5 text-red-600 shadow" title="{{ __('Usuń') }}"><flux:icon.x-mark variant="micro" /></button>
                            </span>
                        @endforeach
                    </div>
                    <flux:error name="newImage" />
                    <flux:error name="images" />
                    <flux:error name="decision" />
                    <div class="grid gap-2 sm:grid-cols-3">
                        <flux:button variant="primary" icon="check" wire:click="decide('approve')" class="!bg-emerald-600 hover:!bg-emerald-700">{{ __('Zatwierdź') }}</flux:button>
                        <flux:button icon="arrow-uturn-left" wire:click="decide('return')">{{ __('Zwróć do poprawy') }}</flux:button>
                        <flux:button variant="danger" icon="x-mark" wire:click="decide('reject')" wire:confirm="{{ __('Odrzucić wniosek? To kończy obieg.') }}">{{ __('Odrzuć') }}</flux:button>
                    </div>
                </flux:card>
            @endif

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Historia') }}</flux:heading>
                <ol class="space-y-4">
                    @foreach ($r->actions as $a)
                        <li wire:key="a-{{ $a->id }}" class="flex gap-3">
                            <flux:avatar size="sm" :initials="$a->user?->initials() ?? '?'" />
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="text-sm">
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $a->user?->name ?? __('System') }}</span>
                                    <span @class(['font-medium', 'text-emerald-600' => $a->action === 'approved', 'text-red-600' => $a->action === 'rejected', 'text-amber-600' => $a->action === 'returned', 'text-zinc-500' => ! in_array($a->action, ['approved', 'rejected', 'returned'])])>{{ $a->label() }}</span>
                                    @if (in_array($a->action, ['approved', 'rejected', 'returned']) && isset($r->stages[$a->stage_position]))
                                        <span class="text-zinc-500">· {{ $r->stages[$a->stage_position]['name'] }}</span>
                                    @endif
                                    <span class="block text-xs text-zinc-400 sm:inline sm:before:content-['_·_']">{{ $a->created_at?->translatedFormat('j M Y, H:i') }}</span>
                                </div>
                                @if ($a->comment)
                                    <div class="mci-prose rounded-mci bg-zinc-50 px-3 py-2 text-sm text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">{!! $a->comment !!}</div>
                                @endif
                                <x-ui.gallery :images="$a->images" class="max-w-sm" />
                            </div>
                        </li>
                    @endforeach
                </ol>
            </flux:card>
        </div>

        <aside class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading>{{ __('Etapy') }}</flux:heading>
                <x-app.workflow-stepper :request="$r" />
                @if ($r->approver_school_id && $r->approver_school_id !== $r->school_id && $r->status->isOpen())
                    <flux:text size="sm" class="inline-flex items-center gap-1"><flux:icon name="building-library" variant="micro" /> {{ __('Decyduje szkoła :school', ['school' => $r->approverSchool?->short_name]) }}</flux:text>
                @endif
            </flux:card>
            @if ($r->tasks->isNotEmpty())
                <flux:card class="space-y-2">
                    <flux:heading>{{ __('Zadania z tego wniosku') }}</flux:heading>
                    @foreach ($r->tasks as $task)
                        @can('view', $task)
                            <a href="{{ route('tasks.show', $task) }}" wire:navigate class="flex items-center gap-2 text-sm hover:underline">
                                <flux:icon :name="$task->status->icon()" variant="micro" @class(['text-emerald-600' => $task->status === \App\Enums\TaskStatus::Done, 'text-zinc-400' => $task->status !== \App\Enums\TaskStatus::Done]) />
                                <span class="truncate">{{ $task->title }}</span>
                            </a>
                        @endcan
                    @endforeach
                </flux:card>
            @endif
        </aside>
    </div>
</div>
