@props(['projects', 'from', 'to', 'links' => true, 'tv' => false])
{{--
    Projects stretched over time: one bar per project from start to end
    (open-ended ones fade out), progress filled in, milestones as diamonds,
    month boundaries and today marked. Scrolls sideways inside on phones.
--}}
@php
    $span = max(1, $from->diffInSeconds($to));
    $pos = fn ($date) => max(0, min(100, $from->diffInSeconds(\Carbon\CarbonImmutable::parse($date), false) / $span * 100));
    $months = [];
    for ($m = $from->startOfMonth()->addMonth(); $m->lessThan($to); $m = $m->addMonth()) {
        $months[] = $m;
    }
    $todayPos = $pos(now());
    $cols = $tv ? 'grid-cols-[24vw_minmax(0,1fr)]' : 'grid-cols-[13rem_minmax(0,1fr)] lg:grid-cols-[16rem_minmax(0,1fr)]';
    $overlay = $tv ? 'start-[calc(24vw+1rem)]' : 'start-[calc(13rem+1rem)] lg:start-[calc(16rem+1rem)]';
    $statusBar = [
        'in_progress' => 'bg-primary/25 ring-1 ring-inset ring-primary/40',
        'planned' => 'bg-zinc-200/70 ring-1 ring-inset ring-dashed ring-zinc-400/60 dark:bg-zinc-700/60',
        'on_hold' => 'bg-amber-200/60 ring-1 ring-inset ring-amber-400/60 dark:bg-amber-500/20',
    ];
@endphp
<div {{ $attributes->class('min-w-0') }} data-project-timeline>
    @if ($projects->isEmpty())
        <div class="flex items-center gap-3 rounded-mci border border-dashed border-zinc-200 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700">
            <flux:icon name="rectangle-stack" class="size-6 shrink-0 text-zinc-300 dark:text-zinc-600" />
            {{ __('Brak trwających projektów w tym okresie.') }}
        </div>
    @else
        <div @class(['-mx-1 px-1 pb-1', 'overflow-x-auto' => ! $tv])>
            <div @class(['min-w-[44rem]' => ! $tv])>
                {{-- scale --}}
                <div class="grid {{ $cols }} gap-4">
                    <div></div>
                    <div @class(['relative font-medium uppercase tracking-wider text-zinc-500', 'h-6 text-[0.6875rem]' => ! $tv, 'h-[2em] text-[0.9em]' => $tv])>
                        <span class="absolute start-0 top-1 first-letter:uppercase">{{ $from->translatedFormat('j M') }}</span>
                        @foreach ($months as $m)
                            <span class="absolute top-1 -translate-x-1/2 whitespace-nowrap first-letter:uppercase" style="left: {{ $pos($m) }}%">{{ $m->translatedFormat('M') }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="relative">
                    <ul @class(['divide-y', 'divide-zinc-100 dark:divide-zinc-800' => ! $tv, 'divide-white/10' => $tv])>
                        @foreach ($projects as $p)
                            @php
                                $start = $p->starts_on ?? $p->created_at ?? $from;
                                $left = $pos($start);
                                $right = $p->ends_on ? $pos($p->ends_on->copy()->endOfDay()) : 100;
                                $width = max(1.5, $right - $left);
                                $progress = $p->progress()['percent'];
                                $tag = $links ? 'a' : 'div';
                            @endphp
                            <li wire:key="tl-{{ $p->id }}" @class(['grid items-center gap-4', $cols, 'py-2.5' => ! $tv, 'py-[1.6vh]' => $tv])>
                                <{{ $tag }} @if ($links) href="{{ route('projects.show', $p) }}" wire:navigate @endif class="group flex min-w-0 items-center gap-2.5">
                                    <span @class(['flex shrink-0 items-center justify-center rounded-mci', 'size-8 bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $tv, 'size-[2.2em] bg-white/10 text-zinc-200' => $tv])><flux:icon :name="$p->kind->icon()" variant="micro" /></span>
                                    <span class="min-w-0">
                                        <span @class(['block truncate font-medium', 'text-sm text-zinc-900 group-hover:text-primary dark:text-white' => ! $tv, 'text-[1.35em] text-white' => $tv])>{{ $p->name }}</span>
                                        <span @class(['block truncate text-zinc-500', 'text-xs' => ! $tv, 'text-[0.95em] dark:text-zinc-400' => $tv])>{{ $p->status->label() }} · {{ $progress }}%</span>
                                    </span>
                                </{{ $tag }}>
                                <div @class(['relative', 'h-7' => ! $tv, 'h-[4.5vh]' => $tv])>
                                    <div @class(['absolute inset-y-0 overflow-hidden rounded-full', $statusBar[$p->status->value] ?? $statusBar['planned'], 'rounded-e-none [mask-image:linear-gradient(to_right,black_85%,transparent)]' => ! $p->ends_on])
                                         style="left: {{ $left }}%; width: {{ $width }}%" title="{{ $p->name }}: {{ $p->datesLabel() }}">
                                        <div @class(['h-full', 'bg-primary/70' => $p->status->value === 'in_progress', 'bg-zinc-400/60' => $p->status->value === 'planned', 'bg-amber-400/70' => $p->status->value === 'on_hold']) style="width: {{ $progress }}%"></div>
                                    </div>
                                    @foreach ($p->milestones as $m)
                                        @if ($m->due_at && $m->due_at->betweenIncluded($from, $to))
                                            <span class="absolute top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rotate-45 ring-2 ring-white dark:ring-zinc-900 {{ $m->completed_at ? 'bg-emerald-500' : ($m->due_at->isPast() ? 'bg-red-500' : 'bg-zinc-700 dark:bg-zinc-200') }}"
                                                  style="left: {{ $pos($m->due_at) }}%" title="{{ $m->title }} · {{ $m->due_at->translatedFormat('j M') }}"></span>
                                        @endif
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    {{-- month lines and today, over the track column --}}
                    <div class="pointer-events-none absolute inset-y-0 end-0 {{ $overlay }}" aria-hidden="true">
                        @foreach ($months as $m)
                            <span @class(['absolute inset-y-0 w-px', 'bg-zinc-200/70 dark:bg-zinc-700/70' => ! $tv, 'bg-white/10' => $tv]) style="left: {{ $pos($m) }}%"></span>
                        @endforeach
                        @if ($todayPos > 0 && $todayPos < 100)
                            <span class="absolute inset-y-0 w-0.5 bg-red-500/80" style="left: {{ $todayPos }}%">
                                <span class="absolute -top-5 -translate-x-1/2 rounded bg-red-500 px-1.5 py-px text-[0.625rem] font-semibold uppercase text-white">{{ __('dziś') }}</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div @class(['mt-3 flex flex-wrap gap-x-4 gap-y-1 text-zinc-500', 'text-xs' => ! $tv, 'text-[0.9em]' => $tv])>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-5 rounded-full bg-primary/60"></span> {{ __('W toku (wypełnienie = postęp)') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span> {{ __('Planowany') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-5 rounded-full bg-amber-300"></span> {{ __('Wstrzymany') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2 rotate-45 bg-zinc-700 dark:bg-zinc-200"></span> {{ __('Kamień milowy') }}</span>
        </div>
    @endif
</div>
