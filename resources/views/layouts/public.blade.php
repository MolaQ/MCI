<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    @include('partials.head')
    <meta name="description" content="{{ __('Szkoły, oferta edukacyjna i publiczne wydarzenia w jednym miejscu.') }}" />
</head>
<body class="flex min-h-screen flex-col bg-zinc-50 antialiased dark:bg-zinc-900">
    @include('partials.demo-banner')
    @php($home = \App\Livewire\Publicly\Home::url())
    @php($manySchools = \App\Models\School::query()->publiclyListed()->count() > 1)
    <header class="sticky top-0 z-30 border-b border-zinc-200/80 bg-white/85 backdrop-blur dark:border-zinc-700/80 dark:bg-zinc-900/85">
        <div class="mci-wrap flex items-center gap-3 py-3">
            <a href="{{ $home }}" class="flex min-w-0 items-center gap-2">
                @if ($appearance->logoUrl())
                    <img src="{{ $appearance->logoUrl() }}" alt="" class="h-8 w-auto" />
                @else
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-mci bg-primary text-[0.625rem] font-bold tracking-tight text-primary-foreground">{{ \Illuminate\Support\Str::limit($appearance->shortName(), 4, '') }}</span>
                @endif
                <span class="flex min-w-0 flex-col leading-tight">
                    <span class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $appearance->shortName() }}</span>
                    <span class="hidden truncate text-xs text-zinc-500 sm:block dark:text-zinc-400">{{ $appearance->schoolName() }}</span>
                </span>
            </a>

            <nav class="ms-auto hidden items-center gap-1 text-sm md:flex" aria-label="{{ __('Sekcje') }}">
                <a href="{{ $home }}#dzis" class="rounded-mci px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Dziś') }}</a>
                <a href="{{ $home }}#kalendarz" class="rounded-mci px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Kalendarz') }}</a>
                <a href="{{ $home }}#komunikaty" class="rounded-mci px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Komunikaty') }}</a>
                <a href="{{ $home }}#wydarzenia" class="rounded-mci px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Wydarzenia') }}</a>
                @if ($manySchools)
                    <a href="{{ $home }}#szkoly" class="rounded-mci px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('Szkoły') }}</a>
                @endif
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
                <div class="mci-wrap flex items-center gap-2 py-1.5 text-xs">
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
        <div class="mci-wrap flex flex-col gap-2 py-6 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ now()->year }} {{ $appearance->shortName() }} · {{ $appearance->schoolName() }}</span>
            <span class="flex flex-wrap gap-4">
                <a href="{{ route('login') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('Logowanie dla pracowników') }}</a>
                <a href="{{ route('tv') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('Tablica TV') }}</a>
            </span>
        </div>
    </footer>

    @fluxScripts
</body>
</html>
