<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
    @php($user = auth()->user())
    @php($school = app(\App\Support\CurrentSchool::class)->get())
    @php($unreadAnnouncements = $school ? $user->unreadAnnouncementsCount($school->id) : 0)

    <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-e border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <x-app.brand />
            <div class="ms-auto hidden lg:block"><livewire:notification-bell /></div>
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <x-app.school-switcher :user="$user" :school="$school" />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Pulpit') }}
            </flux:sidebar.item>
            @if ($school)
                @feature('announcements')
                    <flux:sidebar.item icon="megaphone" :href="route('communication.index')" :current="request()->routeIs('communication.*')" :badge="$unreadAnnouncements ?: null" wire:navigate>
                        {{ __('Komunikaty') }}
                    </flux:sidebar.item>
                @endfeature
                @feature('events')
                    <flux:sidebar.item icon="calendar-days" :href="route('events.index')" :current="request()->routeIs('events.*')" wire:navigate>
                        {{ __('Wydarzenia') }}
                    </flux:sidebar.item>
                @endfeature
                <flux:sidebar.item icon="user-group" :href="route('groups.index')" :current="request()->routeIs('groups.*')" wire:navigate>
                    {{ __('Grupy') }}
                </flux:sidebar.item>
                @if (app(\App\Support\Features::class)->enabled(\App\Enums\Feature::Invites) && $user->can('viewAny', \App\Models\Invite::class))
                    <flux:sidebar.item icon="qr-code" :href="route('invites.index')" :current="request()->routeIs('invites.*')" wire:navigate>
                        {{ __('Zaproszenia') }}
                    </flux:sidebar.item>
                @endif
            @endif
        </flux:sidebar.nav>

        @if ($school && ($user->can('users.manage') || $user->can('settings.manage') || $user->can('categories.manage') || $user->can('audit.view')))
            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Szkoła :name', ['name' => $school->short_name])" class="grid">
                    @can('users.manage')
                        <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>
                            {{ __('Użytkownicy') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('settings.manage')
                        <flux:sidebar.item icon="building-library" :href="route('admin.school')" :current="request()->routeIs('admin.school')" wire:navigate>
                            {{ __('Ustawienia szkoły') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="globe-alt" :href="route('admin.school.profile')" :current="request()->routeIs('admin.school.profile')" wire:navigate>
                            {{ __('Profil publiczny') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.school.features')" :current="request()->routeIs('admin.school.features')" wire:navigate>
                            {{ __('Funkcje szkoły') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('categories.manage')
                        <flux:sidebar.item icon="tag" :href="route('admin.terms')" :current="request()->routeIs('admin.terms')" wire:navigate>
                            {{ __('Kategorie i typy') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('audit.view')
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>
                            {{ __('Logi szkoły') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>
        @endif

        @can('site-admin')
            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Witryna')" class="grid">
                    <flux:sidebar.item icon="building-office-2" :href="route('site.schools')" :current="request()->routeIs('site.schools*')" wire:navigate>
                        {{ __('Szkoły') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="swatch" :href="route('site.appearance')" :current="request()->routeIs('site.appearance')" wire:navigate>
                        {{ __('Wygląd') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('site.features')" :current="request()->routeIs('site.features')" wire:navigate>
                        {{ __('Funkcje') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="tag" :href="route('site.terms')" :current="request()->routeIs('site.terms')" wire:navigate>
                        {{ __('Kategorie i typy') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="book-open" :href="route('site.journal')" :current="request()->routeIs('site.journal')" wire:navigate>
                        {{ __('Dziennik witryny') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        @endcan

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            @feature('public.front')
                <flux:sidebar.item icon="globe-alt" :href="route('home')">{{ __('Portal publiczny') }}</flux:sidebar.item>
            @endfeature
            @feature('public.tv')
                <flux:sidebar.item icon="tv" :href="route('tv')">{{ __('Tryb TV') }}</flux:sidebar.item>
            @endfeature
        </flux:sidebar.nav>

        <x-app.user-menu :user="$user" class="hidden lg:block" />
    </flux:sidebar>

    <flux:header class="lg:hidden border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <x-app.brand class="ms-2" />
        <flux:spacer />
        <livewire:notification-bell />
        <x-app.user-menu :user="$user" compact />
    </flux:header>

    <flux:main>
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" class="mb-6" />
        @endif
        {{ $slot }}
    </flux:main>

    @persist('toast')
        <flux:toast />
    @endpersist

    @fluxScripts
</body>
</html>
