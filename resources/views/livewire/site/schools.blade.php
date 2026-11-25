<div class="space-y-6">
    <x-ui.page-header :title="__('Szkoły')" :subtitle="__('Szkoły korzystające z portalu MCI. Jedna osoba może należeć do kilku szkół.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('site.schools.create')" wire:navigate>{{ __('Nowa szkoła') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->schools as $school)
            <a href="{{ route('site.schools.edit', $school) }}" wire:navigate wire:key="s-{{ $school->id }}"
               class="block rounded-mci border border-zinc-200 bg-white p-4 transition hover:border-primary dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-app.school-badge :school="$school" />
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-800 dark:text-white">{{ $school->name }}</div>
                            <div class="text-xs text-zinc-500">/{{ $school->slug }}</div>
                        </div>
                    </div>
                    @unless ($school->is_active)
                        <flux:badge size="sm" color="zinc">{{ __('Nieaktywna') }}</flux:badge>
                    @endunless
                </div>
                <div class="mt-4 flex gap-4 text-xs text-zinc-500">
                    <span>{{ trans_choice(':count osoba|:count osoby|:count osób', $school->users_count) }}</span>
                    <span>{{ trans_choice(':count grupa|:count grupy|:count grup', $school->groups_count) }}</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
