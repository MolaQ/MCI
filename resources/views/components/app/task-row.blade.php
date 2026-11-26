@props(['task', 'showSchool' => false, 'statuses' => \App\Enums\TaskStatus::cases()])

@php
    $overdue = $task->isOverdue();
    $closed = ! $task->status->isOpen();
    $canWork = auth()->user()->can('work', $task);
    $canCancel = auth()->user()->can('update', $task);
@endphp

{{-- One task on a list (docs/ARCHITECTURE.md §12.2): round status button, title, meta line. --}}
<div {{ $attributes->class(['group flex items-start gap-3 px-4 py-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60']) }} data-task-row="{{ $task->id }}">
    @if ($canWork)
        <button type="button" wire:click="cycle({{ $task->id }})" wire:loading.attr="disabled"
                title="{{ __('Zmień status: :status', ['status' => $task->status->next()->label()]) }}"
                aria-label="{{ __('Zmień status zadania :title na: :status', ['title' => $task->title, 'status' => $task->status->next()->label()]) }}"
                @class(['mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border-2 transition-colors',
                    'border-zinc-300 text-transparent hover:border-primary hover:text-primary/60 dark:border-zinc-500' => $task->status === \App\Enums\TaskStatus::Todo,
                    'border-sky-500 bg-sky-50 text-sky-600 dark:bg-sky-500/10' => $task->status === \App\Enums\TaskStatus::InProgress,
                    'border-emerald-500 bg-emerald-500 text-white' => $task->status === \App\Enums\TaskStatus::Done,
                    'border-zinc-300 bg-zinc-100 text-zinc-400 dark:border-zinc-600 dark:bg-zinc-700' => $task->status === \App\Enums\TaskStatus::Cancelled])>
            <flux:icon :name="match ($task->status) { \App\Enums\TaskStatus::InProgress => 'play', \App\Enums\TaskStatus::Cancelled => 'x-mark', default => 'check' }" variant="micro" class="size-3.5" />
        </button>
    @else
        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-zinc-300 dark:border-zinc-600" title="{{ $task->status->label() }}"></span>
    @endif

    <div class="min-w-0 flex-1">
        <a href="{{ route('tasks.show', $task) }}" wire:navigate @class(['block font-medium leading-snug text-zinc-900 hover:underline dark:text-white', 'text-zinc-400 line-through dark:text-zinc-500' => $closed])>
            {{ $task->title }}
        </a>
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
            @if ($task->status === \App\Enums\TaskStatus::InProgress)
                <span class="inline-flex items-center gap-1 font-medium text-sky-600 dark:text-sky-400"><flux:icon name="play" variant="micro" class="size-3" /> {{ $task->status->label() }}</span>
            @endif
            @if ($task->priority->value >= 3)
                <span @class(['inline-flex items-center gap-1 font-medium', 'text-amber-600 dark:text-amber-400' => $task->priority->value === 3, 'text-red-600 dark:text-red-400' => $task->priority->value === 4])>
                    <flux:icon :name="$task->priority->icon()" variant="micro" class="size-3" /> {{ $task->priority->label() }}
                </span>
            @endif
            @if ($task->due_at)
                <span @class(['inline-flex items-center gap-1', 'font-semibold text-red-600 dark:text-red-400' => $overdue, 'font-medium text-zinc-700 dark:text-zinc-200' => ! $overdue && $task->due_at->isToday()])>
                    <flux:icon :name="$overdue ? 'exclamation-circle' : 'calendar'" variant="micro" class="size-3" />
                    {{ $overdue ? __('po terminie: :due', ['due' => $task->dueLabel()]) : $task->dueLabel() }}
                </span>
            @endif
            @if (($task->checklist_count ?? 0) > 0)
                <span class="inline-flex items-center gap-1 tabular-nums"><flux:icon name="list-bullet" variant="micro" class="size-3" /> {{ $task->checklist_done_count }}/{{ $task->checklist_count }}</span>
            @endif
            @if (($task->images_count ?? 0) > 0)
                <span class="inline-flex items-center gap-1"><flux:icon name="photo" variant="micro" class="size-3" /> {{ $task->images_count }}</span>
            @endif
            @if ($task->is_personal)
                <span class="inline-flex items-center gap-1"><flux:icon name="lock-closed" variant="micro" class="size-3" /> {{ __('osobiste') }}</span>
            @else
                <span class="inline-flex min-w-0 items-center gap-1">
                    <flux:icon name="users" variant="micro" class="size-3 shrink-0" />
                    <span class="truncate">{{ $task->assignees->take(3)->map->label()->join(', ') }}@if ($task->assignees->count() > 3) {{ __('+ :n', ['n' => $task->assignees->count() - 3]) }}@endif</span>
                </span>
                @if ($task->created_by !== auth()->id() && $task->creator)
                    <span class="hidden sm:inline">{{ __('od :name', ['name' => $task->creator->name]) }}</span>
                @endif
            @endif
            @if ($showSchool && $task->school)
                <x-app.school-badge :school="$task->school" />
            @endif
        </div>
    </div>

    @if ($canWork)
        <flux:dropdown position="bottom" align="end">
            <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Status')" class="opacity-60 group-hover:opacity-100" />
            <flux:menu>
                @foreach ($statuses as $status)
                    @continue($status === \App\Enums\TaskStatus::Cancelled && ! $canCancel)
                    <flux:menu.item :icon="$status->icon()" wire:click="setStatus({{ $task->id }}, '{{ $status->value }}')" :disabled="$status === $task->status">{{ $status->label() }}</flux:menu.item>
                @endforeach
                @if ($canCancel)
                    <flux:menu.separator />
                    <flux:menu.item icon="pencil-square" :href="route('tasks.edit', $task)" wire:navigate>{{ __('Edytuj') }}</flux:menu.item>
                @endif
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
