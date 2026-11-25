<x-layouts::public :title="$announcement->title">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-10">
        <a href="{{ route('home') }}#komunikaty" class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"><flux:icon name="arrow-left" variant="micro" /> {{ __('Komunikaty') }}</a>
        <flux:card class="mci-fade-up space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                <x-app.school-badge :school="$announcement->school" size="sm" /> {{ $announcement->school->name }} · {{ $announcement->published_at->translatedFormat('j F Y') }}
            </div>
            <h1 class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $announcement->title }}</h1>
            <div class="mci-prose text-zinc-800 dark:text-zinc-100">{!! $body !!}</div>
            <x-ui.gallery :images="$announcement->images()->where('is_public', true)->get()" />
        </flux:card>
    </div>
</x-layouts::public>
