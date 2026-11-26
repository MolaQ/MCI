<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 p-6">
        <a href="{{ route('login') }}" class="flex flex-col items-center gap-3">
            @if ($appearance->logoUrl())
                <img src="{{ $appearance->logoUrl() }}" alt="" class="h-14 w-auto" />
            @else
                <span class="flex size-14 items-center justify-center rounded-mci bg-primary text-base font-bold tracking-tight text-primary-foreground">
                    {{ \Illuminate\Support\Str::limit($appearance->shortName(), 4, '') }}
                </span>
            @endif
            <span class="text-center text-lg font-bold text-zinc-900 dark:text-white">{{ $appearance->shortName() }}</span>
            <span class="-mt-2 text-center text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ $appearance->schoolName() }}</span>
        </a>

        <div class="w-full max-w-sm">
            <flux:card class="space-y-6">
                {{ $slot }}
            </flux:card>
        </div>
    </div>

    @fluxScripts
</body>
</html>
