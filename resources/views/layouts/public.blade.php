<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    @include('partials.head')
    <meta name="description" content="{{ __('Szkoły, oferta edukacyjna i publiczne wydarzenia w jednym miejscu.') }}" />
</head>
<body class="flex min-h-screen flex-col bg-zinc-50 antialiased dark:bg-zinc-900">
    <header class="sticky top-0 z-30 border-b border-zinc-200/80 bg-white/85 backdrop-blur dark:border-zinc-700/80 dark:bg-zinc-900/85">
        <div class="mx-auto flex max-w-6xl 2xl:max-w-7xl items-center gap-3 px-4 py-3">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2">
                @if ($appearance->logoUrl())
                    <img src="{{ $appearance->logoUrl() }}" alt="" class="h-8 w-auto" />
                @else
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-mci bg-primary text-xs font-bold text-primary-foreground">{{ $appearance->shortName() }}</span>
                @endif
                <span class="hidden truncate text-sm font-semibold text-zinc-800 sm:block dark:text-zinc-100">{{ $appearance->schoolName() }}</span>
            </a>

            <nav class="ms-auto hidden items-center gap-1 text-sm md:flex" aria-label="{{ __('Sekcje') }}">
                <a href="{{ route('home') }}#wydarzenia" class="rounded-mci px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Wydarzenia') }}</a>
                <a href="{{ route('home') }}#szkoly" class="rounded-mci px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Szkoły') }}</a>
                <a href="{{ route('home') }}#komunikaty" class="rounded-mci px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Komunikaty') }}</a>
            </nav>

            <div class="ms-auto flex items-center gap-2 md:ms-2">
@feature('public.tv')
                <flux:button :href="route('tv')" size="sm" variant="ghost" icon="tv" :aria-label="__('Tryb TV')" class="max-sm:!px-2">
                    <span class="max-sm:hidden">{{ __('Tryb TV') }}</span>
                </flux:button>
                @endfeature
                @auth
                    <flux:button :href="route('dashboard')" size="sm" variant="primary" icon="squares-2x2">{{ __('Mój pulpit') }}</flux:button>
                @else
                    <flux:button :href="route('login')" size="sm" variant="primary" icon="arrow-right-end-on-rectangle">{{ __('Zaloguj się') }}</flux:button>
                @endauth
            </div>
        </div>
        @auth
            <div class="border-t border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <div class="mx-auto flex max-w-6xl 2xl:max-w-7xl items-center gap-2 px-4 py-1.5 text-xs">
                    <flux:icon name="eye" variant="micro" />
                    {{ __('Podgląd publiczny: tak tę stronę widzą goście. Nie ma tu żadnych danych wewnętrznych.') }}
                </div>
            </div>
        @endauth
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mx-auto flex max-w-6xl 2xl:max-w-7xl flex-col gap-2 px-4 py-6 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ now()->year }} {{ $appearance->schoolName() }}</span>
            <span class="flex flex-wrap gap-4">
                <a href="{{ route('login') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('Logowanie dla pracowników') }}</a>
                <a href="{{ route('tv') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('Tablica TV') }}</a>
            </span>
        </div>
    </footer>

    @fluxScripts
</body>
</html>
