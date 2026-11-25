@props(['images'])

{{-- Image gallery under a post; click opens the full image in a light box. --}}
@if ($images->isNotEmpty())
    <div x-data="{ shown: null }" {{ $attributes->class(['grid gap-2', 'grid-cols-1' => $images->count() === 1, 'grid-cols-2' => $images->count() > 1]) }}>
        @foreach ($images as $image)
            <button type="button" x-on:click="shown = @js($image->url())" class="overflow-hidden rounded-mci border border-zinc-200 dark:border-zinc-700">
                <img src="{{ $image->url() }}" alt="" width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy"
                     class="{{ $images->count() === 1 ? 'max-h-[28rem] w-full object-contain' : 'aspect-video w-full object-cover' }} transition duration-200 hover:scale-[1.02]" />
            </button>
        @endforeach
        <template x-teleport="body">
            <div x-show="shown" x-cloak x-transition.opacity x-on:click="shown = null" x-on:keydown.escape.window="shown = null"
                 class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4">
                <img :src="shown" alt="" class="max-h-full max-w-full rounded-mci shadow-2xl" />
            </div>
        </template>
    </div>
@endif
