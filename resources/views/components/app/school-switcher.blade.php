@props(['user', 'school'])

@php($schools = app(\App\Support\CurrentSchool::class)->availableFor($user))

@if ($school)
    <flux:dropdown position="bottom" align="start" class="w-full">
        <button type="button" class="flex w-full items-center gap-3 rounded-mci border border-zinc-200 bg-white px-2.5 py-2 text-start transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600"
                @disabled($schools->count() < 2) aria-label="{{ __('Zmień szkołę') }}">
            <x-app.school-badge :school="$school" size="sm" />
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $school->short_name }}</span>
                <span class="block truncate text-xs text-zinc-500">{{ $school->name }}</span>
            </span>
            @if ($schools->count() > 1)
                <flux:icon.chevron-up-down variant="micro" class="text-zinc-400" />
            @endif
        </button>
        @if ($schools->count() > 1)
            <flux:menu class="min-w-64">
                <flux:menu.heading>{{ __('Przełącz szkołę') }}</flux:menu.heading>
                @foreach ($schools as $s)
                    <form method="POST" action="{{ route('school.switch') }}">
                        @csrf
                        <input type="hidden" name="school_id" value="{{ $s->id }}">
                        <flux:menu.item as="button" type="submit" class="w-full" :icon="$s->id === $school->id ? 'check' : null">
                            {{ $s->name }}
                        </flux:menu.item>
                    </form>
                @endforeach
            </flux:menu>
        @endif
    </flux:dropdown>
@else
    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Nie należysz do żadnej szkoły')" :text="__('Użyj linku lub kodu QR z zaproszenia.')" />
@endif
