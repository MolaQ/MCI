@props(['school', 'size' => 'md'])

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center rounded-mci font-bold tracking-tight text-white',
    'size-9 text-[0.65rem]' => $size === 'md',
    'size-6 text-[0.55rem]' => $size === 'sm',
]) }} style="background-color: {{ \App\Support\Appearance::sanitizeHex($school->color, '#3f3f46') }}">
    {{ \Illuminate\Support\Str::limit($school->short_name, 4, '') }}
</span>
