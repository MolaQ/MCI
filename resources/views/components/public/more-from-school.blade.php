@props(['school', 'more'])

{{-- Side column of a public detail page: more public items of the same school. --}}
<aside class="space-y-4" aria-label="{{ __('Więcej ze szkoły') }}">
    <a href="{{ route('public.school', $school->slug) }}" class="group flex items-center gap-3 rounded-mci border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800/70">
        <x-app.school-badge :school="$school" />
        <span class="min-w-0 flex-1 font-semibold text-zinc-900 group-hover:text-primary dark:text-white">{{ $school->name }}</span>
        <flux:icon name="arrow-right" variant="micro" class="text-zinc-400" />
    </a>

    @if ($more['announcements']->isNotEmpty())
        <section class="rounded-mci border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/70">
            <h2 class="flex items-center gap-2 border-b border-zinc-100 px-4 py-3 text-sm font-semibold text-zinc-900 dark:border-zinc-700/60 dark:text-white"><flux:icon name="megaphone" variant="mini" class="text-zinc-500" /> {{ __('Inne komunikaty') }}</h2>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                @foreach ($more['announcements'] as $a)
                    <a href="{{ route('public.announcement', $a) }}" class="group block px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div class="text-xs text-zinc-500">{{ $a->published_at->translatedFormat('j M Y') }}</div>
                        <div class="line-clamp-2 text-sm font-medium text-zinc-900 group-hover:text-primary dark:text-white">{{ $a->title }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($more['events']->isNotEmpty())
        <section class="rounded-mci border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/70">
            <h2 class="flex items-center gap-2 border-b border-zinc-100 px-4 py-3 text-sm font-semibold text-zinc-900 dark:border-zinc-700/60 dark:text-white"><flux:icon name="calendar-days" variant="mini" class="text-zinc-500" /> {{ __('Najbliższe wydarzenia') }}</h2>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                @foreach ($more['events'] as $e)
                    <a href="{{ route('public.event', $e) }}" class="group flex items-center gap-3 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <x-public.date-badge :date="$e->starts_at" class="!w-12 !py-1" />
                        <span class="line-clamp-2 min-w-0 text-sm font-medium text-zinc-900 group-hover:text-primary dark:text-white">{{ $e->title }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</aside>
