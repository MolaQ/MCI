@props(['property', 'label' => null, 'placeholder' => null])

{{-- Shared rich-text editor with @mentions (docs/ARCHITECTURE.md §12.1). --}}
<div {{ $attributes->class('space-y-2') }}>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div wire:ignore
         x-data="mciEditor({ property: @js($property), searchUrl: @js(route('mentions.search')), placeholder: @js($placeholder), label: @js($label) })"
         class="overflow-hidden rounded-mci border border-zinc-200 bg-white shadow-xs transition-shadow focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 dark:border-zinc-600 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-0.5 border-b border-zinc-100 px-1.5 py-1 dark:border-zinc-700" role="toolbar" aria-label="{{ __('Formatowanie') }}">
            @foreach ([
                ['bold', 'bold', __('Pogrubienie')],
                ['italic', 'italic', __('Kursywa')],
                ['strike', 'strikethrough', __('Przekreślenie')],
                ['h2', 'h2', __('Nagłówek')],
                ['bullet', 'list-bullet', __('Lista')],
                ['ordered', 'numbered-list', __('Lista numerowana')],
                ['quote', 'chat-bubble-bottom-center-text', __('Cytat')],
            ] as [$action, $icon, $title])
                @php($check = match ($action) { 'h2' => "isActive('heading', { level: 2 })", 'bullet' => "isActive('bulletList')", 'ordered' => "isActive('orderedList')", 'quote' => "isActive('blockquote')", default => "isActive('{$action}')" })
                <button type="button" x-on:click="run('{{ $action }}')" title="{{ $title }}" aria-label="{{ $title }}"
                        x-bind:class="{{ $check }} ? 'bg-zinc-200/80 text-zinc-900 dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800'"
                        class="flex size-8 items-center justify-center rounded-md transition-colors">
                    <flux:icon :name="$icon" variant="micro" />
                </button>
            @endforeach
            <button type="button" x-on:click="link()" title="{{ __('Link') }}" aria-label="{{ __('Link') }}"
                    class="flex size-8 items-center justify-center rounded-md text-zinc-500 transition-colors hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                <flux:icon name="link" variant="micro" />
            </button>
            <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-zinc-700"></span>
            <button type="button" x-on:click="run('mention')" title="{{ __('Wspomnij osobę lub grupę') }}"
                    class="flex h-8 items-center gap-1 rounded-md px-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                <flux:icon name="at-symbol" variant="micro" /> {{ __('Wspomnij') }}
            </button>
        </div>
        <div x-ref="content" class="text-sm text-zinc-800 dark:text-zinc-100">
            <div x-show="! ready" class="min-h-40 px-3 py-2 text-zinc-400">{{ __('Ładowanie edytora…') }}</div>
        </div>
    </div>
    <flux:error :name="$property" />
</div>
