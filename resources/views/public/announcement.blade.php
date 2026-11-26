<x-layouts::public :title="$announcement->title">
    <div class="mci-wrap space-y-6 py-10">
        <a href="{{ \App\Livewire\Publicly\Home::url('komunikaty') }}" class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"><flux:icon name="arrow-left" variant="micro" /> {{ __('Komunikaty') }}</a>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] 2xl:grid-cols-[minmax(0,1fr)_24rem]">
            <flux:card class="mci-fade-up min-w-0 space-y-4">
                <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                    <x-app.school-badge :school="$announcement->school" size="sm" /> {{ $announcement->school->name }} · {{ $announcement->published_at->translatedFormat('j F Y') }}
                </div>
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $announcement->title }}</h1>
                <div class="mci-prose max-w-4xl text-zinc-800 dark:text-zinc-100">{!! $body !!}</div>
                <x-ui.gallery :images="$announcement->images()->where('is_public', true)->get()" />
            </flux:card>
            <x-public.more-from-school :school="$announcement->school" :more="$more" />
        </div>
    </div>
</x-layouts::public>
