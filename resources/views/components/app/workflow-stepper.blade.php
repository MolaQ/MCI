@props(['request', 'compact' => false])

{{-- Stage progress of a request: done ✓, current (pulsing while waiting), later. --}}
<ol {{ $attributes->class(['flex items-center gap-1' => $compact, 'space-y-0' => ! $compact]) }} aria-label="{{ __('Etapy') }}">
    @foreach ($request->stages as $i => $stage)
        @php
            $status = $request->status;
            $finished = $status === \App\Enums\WorkflowStatus::Approved;
            $done = $finished || $i < $request->current_position;
            $current = ! $finished && $i === $request->current_position;
            $state = match (true) {
                $done => 'done',
                $current && $status === \App\Enums\WorkflowStatus::Rejected => 'rejected',
                $current && $status === \App\Enums\WorkflowStatus::Returned => 'returned',
                $current && $status === \App\Enums\WorkflowStatus::Cancelled => 'cancelled',
                $current => 'current',
                default => 'later',
            };
        @endphp
        @if ($compact)
            <li title="{{ $stage['name'] }}" @class(['h-1.5 flex-1 rounded-full',
                'bg-emerald-500' => $state === 'done', 'bg-sky-500 motion-safe:animate-pulse' => $state === 'current',
                'bg-red-500' => $state === 'rejected', 'bg-amber-500' => $state === 'returned',
                'bg-zinc-200 dark:bg-zinc-700' => in_array($state, ['later', 'cancelled'])])></li>
        @else
            <li class="relative flex gap-3 pb-5 last:pb-0">
                @if (! $loop->last)
                    <span @class(['absolute start-3 top-7 -bottom-0.5 w-px', 'bg-emerald-400' => $done, 'bg-zinc-200 dark:bg-zinc-700' => ! $done])></span>
                @endif
                <span @class(['relative flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-emerald-500 text-white' => $state === 'done',
                    'bg-sky-500 text-white ring-4 ring-sky-500/20' => $state === 'current',
                    'bg-red-500 text-white' => $state === 'rejected',
                    'bg-amber-500 text-white' => $state === 'returned',
                    'border border-zinc-300 bg-white text-zinc-500 dark:border-zinc-600 dark:bg-zinc-800' => in_array($state, ['later', 'cancelled'])])>
                    @if ($state === 'done')
                        <flux:icon.check variant="micro" />
                    @elseif ($state === 'rejected')
                        <flux:icon.x-mark variant="micro" />
                    @elseif ($state === 'returned')
                        <flux:icon.arrow-uturn-left variant="micro" />
                    @else
                        {{ $i + 1 }}
                    @endif
                </span>
                <div class="min-w-0 pt-0.5">
                    <div @class(['text-sm font-medium', 'text-zinc-900 dark:text-white' => $state !== 'later', 'text-zinc-500' => $state === 'later'])>{{ $stage['name'] }}</div>
                    <div class="text-xs text-zinc-500">
                        {{ $stage['approver_label'] }}
                        @if ($stage['sla_hours'])
                            · {{ trans_choice(':count godz. na decyzję|:count godz. na decyzję|:count godz. na decyzję', $stage['sla_hours']) }}
                        @endif
                    </div>
                    @if ($state === 'current' && $request->stage_due_at)
                        <div @class(['mt-0.5 text-xs font-medium', 'text-red-600' => $request->isOverdue(), 'text-sky-600 dark:text-sky-400' => ! $request->isOverdue()])>
                            {{ $request->isOverdue() ? __('termin minął :date', ['date' => $request->stage_due_at->translatedFormat('j M, H:i')]) : __('decyzja do :date', ['date' => $request->stage_due_at->translatedFormat('j M, H:i')]) }}
                        </div>
                    @endif
                </div>
            </li>
        @endif
    @endforeach
</ol>
