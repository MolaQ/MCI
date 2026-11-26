@php($t = $this->template)
<div class="space-y-6">
    <x-ui.page-header :title="$t ? $t->name : __('Nowy wniosek')" :subtitle="$t ? ($t->description ?: __('Wypełnij wniosek. Po złożeniu przejdzie przez etapy pokazane obok.')) : __('Wybierz rodzaj wniosku.')">
        <x-slot:actions>
            @if ($t)
                <flux:button wire:click="back" variant="ghost" icon="arrow-left">{{ __('Inny szablon') }}</flux:button>
            @else
                <flux:button :href="route('workflows.index')" wire:navigate variant="ghost" icon="arrow-left">{{ __('Obiegi') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $t)
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->templates as $template)
                <button type="button" wire:click="choose({{ $template->id }})" wire:key="tpl-{{ $template->id }}"
                        class="group flex items-start gap-3 rounded-mci border border-zinc-200 bg-white p-4 text-start transition hover:border-primary hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-mci bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-primary-foreground"><flux:icon :name="$template->icon" variant="mini" /></span>
                    <span class="min-w-0 space-y-1">
                        <span class="block font-medium text-zinc-900 dark:text-white">{{ $template->name }}</span>
                        @if ($template->description)
                            <span class="line-clamp-2 block text-sm text-zinc-500">{{ $template->description }}</span>
                        @endif
                        <span class="flex flex-wrap gap-x-3 text-xs text-zinc-500">
                            <span>{{ trans_choice(':count etap|:count etapy|:count etapów', $template->stages_count) }}</span>
                            @if ($template->school_id === null)<span class="inline-flex items-center gap-1"><flux:icon name="globe-alt" variant="micro" class="size-3" /> {{ __('wspólny') }}</span>@endif
                        </span>
                    </span>
                </button>
            @empty
                <flux:card class="sm:col-span-2 lg:col-span-3">
                    <x-ui.empty-state icon="clipboard-document-check" :title="__('Brak szablonów')" :description="__('Szablony wniosków przygotowuje administrator szkoły lub portalu.')" />
                </flux:card>
            @endforelse
        </div>
    @else
        <form wire:submit="submit" class="grid gap-6 lg:grid-cols-3">
            <flux:card class="space-y-5 lg:col-span-2">
                <flux:input wire:model="title" :label="__('Tytuł wniosku') . ' *'" :placeholder="__('np. Wycieczka 2b do Krakowa, 12 maja')" maxlength="200" />
                @foreach ($t->fields ?? [] as $field)
                    <div wire:key="f-{{ $field['key'] }}"><x-app.workflow-field :field="$field" :model="'data.'.$field['key']" /></div>
                @endforeach

                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:label>{{ __('Załączniki') }} <span class="font-normal text-zinc-400">({{ count($images) }}/{{ config('mci.media.max_post_images') }}{{ $t->min_attachments ? ', '.__('wymagane: :n', ['n' => $t->min_attachments]) : '' }})</span></flux:label>
                        @if (count($images) < config('mci.media.max_post_images'))
                            <x-ui.image-editor model="newImage" gallery :presets="['free', '4:3', '1:1']" :max-size="2000" :background="false" />
                        @endif
                    </div>
                    @if ($images)
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($images as $i => $image)
                                <div wire:key="img-{{ $i }}-{{ $image->getFilename() }}" class="relative overflow-hidden rounded-mci border border-zinc-200 dark:border-zinc-700">
                                    <img src="{{ $image->temporaryUrl() }}" alt="" class="aspect-video w-full object-cover" />
                                    <button type="button" wire:click="removeImage({{ $i }})" class="absolute end-1.5 bottom-1.5 rounded bg-white/90 p-1 text-red-600" title="{{ __('Usuń') }}"><flux:icon.trash variant="micro" /></button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <flux:text size="sm">{{ __('Skany, zdjęcia dokumentów, faktury (PNG, JPG, WebP).') }}</flux:text>
                    <flux:error name="newImage" />
                    <flux:error name="images" />
                </div>
            </flux:card>

            <flux:card class="h-fit space-y-5 lg:sticky lg:top-4">
                <flux:heading>{{ __('Ścieżka wniosku') }}</flux:heading>
                <ol class="space-y-3">
                    @foreach ($t->stages as $i => $stage)
                        <li class="flex gap-3">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full border border-zinc-300 text-xs font-semibold text-zinc-500 dark:border-zinc-600">{{ $i + 1 }}</span>
                            <div class="text-sm">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $stage->name }}</div>
                                <div class="text-xs text-zinc-500">{{ \App\Models\WorkflowStage::approverLabel($stage->approver_type, $stage->approver_id) }}@if ($stage->sla_hours) · {{ trans_choice(':count godz.|:count godz.|:count godz.', $stage->sla_hours) }}@endif</div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if ($errors->any())
                    <div role="alert" class="space-y-1 rounded-mci border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                        <p class="flex items-center gap-1.5 font-medium"><flux:icon name="exclamation-triangle" variant="micro" /> {{ __('Wniosek nie został złożony:') }}</p>
                        <ul class="list-disc space-y-0.5 ps-5">
                            @foreach (array_unique($errors->all()) as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <flux:button type="submit" variant="primary" icon="paper-airplane" class="w-full">{{ __('Złóż wniosek') }}</flux:button>
            </flux:card>
        </form>
    @endif
</div>
