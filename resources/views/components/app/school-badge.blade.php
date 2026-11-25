@props(['school', 'size' => 'md'])

@php($logo = $school->getAttribute('logo_media_id') || $school->getAttribute('logo_path') ? $school->logoUrl() : null)
@if ($logo)
    <span {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-mci bg-white ring-1 ring-zinc-200 dark:ring-zinc-700',
        'size-9' => $size === 'md',
        'size-6' => $size === 'sm',
    ]) }}>
        <img src="{{ $logo }}" alt="" class="size-full object-contain" loading="lazy" />
    </span>
@else
    <span {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-mci font-bold tracking-tight text-white',
        'size-9 text-[0.65rem]' => $size === 'md',
        'size-6 text-[0.55rem]' => $size === 'sm',
    ]) }} style="background-color: {{ \App\Support\Appearance::sanitizeHex($school->color, '#3f3f46') }}">
        {{ \Illuminate\Support\Str::limit($school->short_name, 4, '') }}
    </span>
@endif
