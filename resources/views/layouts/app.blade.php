<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
    @php($user = auth()->user())

    <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-e border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <x-app.brand />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Pulpit') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="user-group" :href="route('groups.index')" :current="request()->routeIs('groups.*')" wire:navigate>
                {{ __('Grupy') }}
            </flux:sidebar.item>
        </flux:sidebar.nav>

        @if ($user->can('users.manage') || $user->can('settings.manage') || $user->can('audit.view'))
            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Administracja')" class="grid">
                    @can('users.manage')
                        <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>
                            {{ __('Użytkownicy') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('settings.manage')
                        <flux:sidebar.item icon="swatch" :href="route('admin.appearance')" :current="request()->routeIs('admin.appearance')" wire:navigate>
                            {{ __('Wygląd') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('audit.view')
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>
                            {{ __('Dziennik zdarzeń') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>
        @endif

        <flux:sidebar.spacer />

        <x-app.user-menu :user="$user" class="hidden lg:block" />
    </flux:sidebar>

    <flux:header class="lg:hidden border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <x-app.brand class="ms-2" />
        <flux:spacer />
        <x-app.user-menu :user="$user" compact />
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    @persist('toast')
        <flux:toast />
    @endpersist

    @fluxScripts
</body>
</html>
