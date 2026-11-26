@props(['month', 'days', 'nav' => true, 'compact' => false, 'navigate' => true])
{{--
    Month grid, Monday first. $days: items keyed by Y-m-d (Agenda::byDay).
    Wide screens show chips in the cells; phones show dots, and the list of
    the chosen day below the grid (chosen with Alpine, no round trip).
--}}
@php($weeks = \App\Domain\Calendar\Agenda::monthGrid($month))
@php($today = now()->toDateString())
@php($first = $month->isSameMonth(now()) ? $today : (array_key_first($days) ?? $month->toDateString()))
@php($names = [__('Pn'), __('Wt'), __('Śr'), __('Cz'), __('Pt'), __('So'), __('Nd')])
<div {{ $attributes->class('space-y-4') }} x-data="{ sel: window.matchMedia('(min-width: 40rem)').matches ? null : @js($first) }" data-month-calendar>
    <div class="flex flex-wrap items-center gap-3">
        <h3 class="text-lg font-semibold tracking-tight text-zinc-900 first-letter:uppercase dark:text-white">{{ $month->translatedFormat('F Y') }}</h3>
        @if ($nav)
            <div class="flex items-center gap-1" role="group" aria-label="{{ __('Zmiana miesiąca') }}">
                <flux:button size="xs" variant="ghost" icon="chevron-left" wire:click="shiftMonth(-1)" :aria-label="__('Poprzedni miesiąc')" />
                @unless ($month->isSameMonth(now()))
                    <flux:button size="xs" variant="ghost" wire:click="shiftMonth(0)">{{ __('Dziś') }}</flux:button>
                @endunless
                <flux:button size="xs" variant="ghost" icon="chevron-right" wire:click="shiftMonth(1)" :aria-label="__('Następny miesiąc')" />
            </div>
        @endif
        @isset($actions)
            <div class="ms-auto flex items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    <div class="overflow-hidden rounded-mci border border-zinc-200 dark:border-zinc-700" wire:loading.class="opacity-60" wire:target="shiftMonth">
        <div class="grid grid-cols-7 border-b border-zinc-200 bg-zinc-50 text-center text-[0.6875rem] font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/60">
            @foreach ($names as $i => $n)
                <div @class(['py-2', 'text-zinc-400' => $i >= 5])>{{ $n }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7 gap-px bg-zinc-200 dark:bg-zinc-700">
            @foreach ($weeks as $week)
                @foreach ($week as $d)
                    @php($key = $d->toDateString())
                    @php($dayItems = $days[$key] ?? collect())
                    @php($inMonth = $d->isSameMonth($month))
                    <button type="button" x-on:click="sel = @js($key)" data-day="{{ $key }}"
                        :class="sel === @js($key) ? 'ring-2 ring-inset ring-primary/60 z-10' : ''"
                        @class(['relative flex flex-col items-stretch gap-1 p-1.5 text-start transition focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-primary sm:p-2',
                                $compact ? 'min-h-14 sm:min-h-20' : 'min-h-14 sm:min-h-24 xl:min-h-28',
                                'bg-white hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-800' => $inMonth,
                                'bg-zinc-50 text-zinc-400 dark:bg-zinc-900/60' => ! $inMonth])
                        aria-label="{{ $d->translatedFormat('j F') }}{{ $dayItems->isNotEmpty() ? ', '.trans_choice(':count pozycja|:count pozycje|:count pozycji', $dayItems->count()) : '' }}">
                        <span @class(['flex size-6 items-center justify-center rounded-full text-xs font-semibold tabular-nums sm:size-7 sm:text-sm',
                                      'bg-primary text-primary-foreground' => $key === $today,
                                      'text-zinc-800 dark:text-zinc-100' => $key !== $today && $inMonth,
                                      'text-zinc-400 dark:text-zinc-500' => $key !== $today && ! $inMonth,
                                      'text-zinc-500 dark:text-zinc-400' => $key !== $today && $inMonth && $d->isWeekend()])>{{ $d->day }}</span>
                        @if ($dayItems->isNotEmpty())
                            {{-- phones: dots --}}
                            <span class="flex flex-wrap gap-0.5 sm:hidden">
                                @foreach ($dayItems->take(4) as $item)
                                    <span class="size-1.5 rounded-full {{ $item->tone() }}"></span>
                                @endforeach
                            </span>
                            {{-- wider: chips --}}
                            <span class="hidden min-w-0 flex-col gap-0.5 sm:flex">
                                @foreach ($dayItems->take($compact ? 2 : 3) as $item)
                                    <span @class(['block truncate rounded px-1.5 py-0.5 text-[0.6875rem] font-medium leading-tight', $item->tone('chip'), 'line-through opacity-70' => $item->done])>
                                        @unless ($item->allDay || $item->isMultiDay())<span class="tabular-nums opacity-70">{{ $item->start->format('H:i') }}</span>@endunless
                                        {{ $item->title }}
                                    </span>
                                @endforeach
                                @if ($dayItems->count() > ($compact ? 2 : 3))
                                    <span class="px-1.5 text-[0.6875rem] text-zinc-500">+{{ $dayItems->count() - ($compact ? 2 : 3) }}</span>
                                @endif
                            </span>
                        @endif
                    </button>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- The chosen day --}}
    <div class="rounded-mci bg-zinc-50 p-3 dark:bg-zinc-800/50" aria-live="polite" x-show="sel" x-cloak>
        @foreach ($weeks as $week)
            @foreach ($week as $d)
                @php($key = $d->toDateString())
                <div x-show="sel === @js($key)" x-cloak>
                    <div class="mb-1 flex items-center justify-between gap-2 px-2">
                        <span class="text-sm font-semibold text-zinc-700 first-letter:uppercase dark:text-zinc-200">{{ $d->translatedFormat('l, j F') }}</span>
                        <button type="button" x-on:click="sel = null" class="hidden rounded p-1 text-zinc-400 hover:text-zinc-700 sm:block" aria-label="{{ __('Zamknij') }}"><flux:icon name="x-mark" variant="micro" /></button>
                    </div>
                    <x-calendar.agenda :items="$days[$key] ?? collect()" :day="$d" :limit="20" :navigate="$navigate" :empty="__('Tego dnia nic nie zaplanowano.')" />
                </div>
            @endforeach
        @endforeach
    </div>
</div>
