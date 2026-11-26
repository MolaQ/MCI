<div class="space-y-6" wire:poll.60s.visible>
    <x-ui.page-header :title="__('Obiegi i wnioski')" :subtitle="__('Wnioski przechodzą kolejne etapy akceptacji. Tu decydujesz i śledzisz swoje sprawy.')">
        <x-slot:actions>
            <flux:button :href="route('workflows.create')" wire:navigate variant="primary" icon="plus">{{ __('Nowy wniosek') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="{{ __('Filtry wniosków') }}">
            <x-ui.pill wire:click="setFilter('view', 'decide')" :active="$view === 'decide'" :count="$this->counts['decide']"
                :class="$view !== 'decide' && $this->counts['decide'] > 0 ? '!border-sky-300 !text-sky-700 dark:!border-sky-500/50 dark:!text-sky-300' : ''">
                <flux:icon name="hand-raised" variant="micro" /> {{ __('Do mojej decyzji') }}
            </x-ui.pill>
            <x-ui.pill wire:click="setFilter('view', 'mine')" :active="$view === 'mine'" :count="$this->counts['mine']"><flux:icon name="paper-airplane" variant="micro" /> {{ __('Złożone przeze mnie') }}</x-ui.pill>
            <x-ui.pill wire:click="setFilter('view', 'all')" :active="$view === 'all'"><flux:icon name="rectangle-stack" variant="micro" /> {{ __('Wszystkie') }}</x-ui.pill>
            @if ($view !== 'decide')
                <span class="mx-1 hidden h-5 w-px bg-zinc-200 sm:block dark:bg-zinc-700"></span>
                @foreach (['open' => ['arrow-path', __('W toku')], 'done' => ['check-badge', __('Zakończone')], 'rejected' => ['x-circle', __('Odrzucone')]] as $key => [$icon, $label])
                    <x-ui.pill wire:click="setFilter('status', '{{ $status === $key ? '' : $key }}')" :active="$status === $key"><flux:icon :name="$icon" variant="micro" /> {{ $label }}</x-ui.pill>
                @endforeach
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($this->templates->isNotEmpty())
                <flux:dropdown>
                    <x-ui.pill :active="$template !== ''">
                        <flux:icon name="clipboard-document-check" variant="micro" />
                        {{ $template !== '' ? ($this->templates->firstWhere('id', (int) $template)?->name ?? __('Szablon')) : __('Każdy szablon') }}
                        <flux:icon name="chevron-down" variant="micro" class="opacity-60" />
                    </x-ui.pill>
                    <flux:menu>
                        <flux:menu.item wire:click="setFilter('template', '')">{{ __('Każdy szablon') }}</flux:menu.item>
                        <flux:menu.separator />
                        @foreach ($this->templates as $t)
                            <flux:menu.item :icon="$t->icon" wire:click="setFilter('template', '{{ $t->id }}')">{{ $t->name }}</flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endif
            @if ($multiSchool)
                <x-ui.pill wire:click="$toggle('allSchools')" :active="$allSchools"><flux:icon name="building-library" variant="micro" /> {{ __('Wszystkie moje szkoły') }}</x-ui.pill>
            @endif
        </div>
    </div>

    @if ($this->counts['returned'] > 0 && $view !== 'mine')
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-mci border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <span class="inline-flex items-center gap-2 font-medium"><flux:icon name="arrow-uturn-left" variant="mini" /> {{ trans_choice(':count Twój wniosek wrócił do poprawy|:count Twoje wnioski wróciły do poprawy|:count Twoich wniosków wróciło do poprawy', $this->counts['returned']) }}</span>
            <flux:button size="sm" wire:click="setFilter('view', 'mine')">{{ __('Pokaż') }}</flux:button>
        </div>
    @endif

    <div class="grid gap-3" wire:loading.class="opacity-60" wire:target="setFilter,allSchools">
        @forelse ($this->requests as $r)
            <a href="{{ route('workflows.show', $r) }}" wire:navigate wire:key="r-{{ $r->id }}"
               class="group block rounded-mci border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-mci bg-primary/10 text-primary"><flux:icon :name="$r->template?->icon ?? 'clipboard-document-check'" variant="mini" /></span>
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-zinc-900 group-hover:underline dark:text-white">{{ $r->title }}</span>
                            <flux:badge size="sm" :color="$r->status->color()" :icon="$r->status->icon()">{{ $r->status->label() }}</flux:badge>
                            @if ($r->isOverdue())
                                <flux:badge size="sm" color="red" icon="clock">{{ __('po terminie') }}</flux:badge>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                            <span>{{ $r->template_name }}</span>
                            <span>{{ $r->requester?->name ?? __('—') }}</span>
                            @if ($r->status->isOpen() && $r->currentStage())
                                <span class="inline-flex items-center gap-1"><flux:icon name="arrow-right-circle" variant="micro" class="size-3" /> {{ __('Etap :n/:of: :stage', ['n' => $r->current_position + 1, 'of' => count($r->stages), 'stage' => $r->currentStage()['name']]) }}</span>
                            @endif
                            @if ($r->stage_due_at && $r->status === \App\Enums\WorkflowStatus::InProgress)
                                <span @class(['font-medium', 'text-red-600' => $r->isOverdue()])>{{ __('do :date', ['date' => $r->stage_due_at->translatedFormat('j M, H:i')]) }}</span>
                            @endif
                            <span>{{ $r->updated_at->diffForHumans() }}</span>
                            @if ($allSchools || $r->school_id !== app(\App\Support\CurrentSchool::class)->id())
                                <x-app.school-badge :school="$r->school" size="sm" />
                            @endif
                        </div>
                        <x-app.workflow-stepper :request="$r" compact class="max-w-xs pt-1" />
                    </div>
                </div>
            </a>
        @empty
            <flux:card>
                <x-ui.empty-state icon="clipboard-document-check"
                    :title="$view === 'decide' ? __('Nic nie czeka na Twoją decyzję') : __('Brak wniosków')"
                    :description="$view === 'decide' ? __('Gdy ktoś złoży wniosek do Ciebie, pojawi się tutaj i w dzwonku.') : __('Złóż nowy wniosek z gotowego szablonu.')" />
            </flux:card>
        @endforelse
    </div>

    {{ $this->requests->links() }}
</div>
