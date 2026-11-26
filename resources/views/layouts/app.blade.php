<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
    @php($user = auth()->user())
    @php($school = app(\App\Support\CurrentSchool::class)->get())
    @php($unreadAnnouncements = $school ? $user->unreadAnnouncementsCount($school->id) : 0)
    @php($pendingDecisions = $school && app(\App\Support\Features::class)->enabled(\App\Enums\Feature::Workflows) ? \App\Models\WorkflowRequest::query()->awaitingDecisionBy($user)->count() : 0)
    @php($openTasks = $school && app(\App\Support\Features::class)->enabled(\App\Enums\Feature::Tasks) ? \App\Models\Task::query()->assignedTo($user)->where('tasks.school_id', $school->id)->open()->whereNotNull('tasks.due_at')->where('tasks.due_at', '<=', now()->endOfDay())->count() : 0)

    <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-e border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <x-app.brand />
            <div class="ms-auto hidden lg:block"><livewire:notification-bell /></div>
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        @unless (\App\Models\School::isSingle() && $school)
            <x-app.school-switcher :user="$user" :school="$school" />
        @endunless

        @php($features = app(\App\Support\Features::class))
        @php($showAnnouncements = $school && $features->enabled(\App\Enums\Feature::Announcements))
        @php($showEvents = $school && $features->enabled(\App\Enums\Feature::Events))
        @php($showTasks = $school && $features->enabled(\App\Enums\Feature::Tasks))
        @php($showWorkflows = $school && $features->enabled(\App\Enums\Feature::Workflows))
        @php($showProjects = $school && $features->enabled(\App\Enums\Feature::Projects))
        @php($showInvites = $school && $features->enabled(\App\Enums\Feature::Invites) && $user->can('viewAny', \App\Models\Invite::class))
        @php($schoolAdmin = $school && ($user->can('users.manage') || $user->can('settings.manage') || $user->can('audit.view') || $user->can('categories.manage') || ($user->can('workflows.manage') && $showWorkflows)))

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Pulpit') }}
            </flux:sidebar.item>
        </flux:sidebar.nav>

        <nav class="grid gap-1" aria-label="{{ __('Moduły') }}">
            @if ($showAnnouncements || $showEvents)
                <x-app.nav-section key="news" :heading="__('Informacje')" icon="megaphone" :badge="$unreadAnnouncements ?: null"
                                   :active="request()->routeIs('communication.*', 'events.*')">
                    @if ($showAnnouncements)
                        <flux:sidebar.item icon="megaphone" :href="route('communication.index')" :current="request()->routeIs('communication.*')" :badge="$unreadAnnouncements ?: null" wire:navigate>
                            {{ __('Komunikaty') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($showEvents)
                        <flux:sidebar.item icon="calendar-days" :href="route('events.index')" :current="request()->routeIs('events.*')" wire:navigate>
                            {{ __('Wydarzenia') }}
                        </flux:sidebar.item>
                    @endif
                </x-app.nav-section>
            @endif

            @if ($showTasks || $showWorkflows || $showProjects)
                <x-app.nav-section key="work" :heading="__('Praca')" icon="briefcase" :badge="($openTasks + $pendingDecisions) ?: null"
                                   :active="request()->routeIs('tasks.*', 'workflows.*', 'projects.*')">
                    @if ($showTasks)
                        <flux:sidebar.item icon="check-circle" :href="route('tasks.index')" :current="request()->routeIs('tasks.*')" :badge="$openTasks ?: null" wire:navigate>
                            {{ __('Zadania') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($showWorkflows)
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('workflows.index')" :current="request()->routeIs('workflows.*')" :badge="$pendingDecisions ?: null" wire:navigate>
                            {{ __('Obiegi') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($showProjects)
                        <flux:sidebar.item icon="rectangle-stack" :href="route('projects.index')" :current="request()->routeIs('projects.*')" wire:navigate>
                            {{ __('Projekty') }}
                        </flux:sidebar.item>
                    @endif
                </x-app.nav-section>
            @endif

            @if ($school)
                <x-app.nav-section key="people" :heading="__('Ludzie')" icon="user-group" :active="request()->routeIs('groups.*', 'invites.*')">
                    <flux:sidebar.item icon="user-group" :href="route('groups.index')" :current="request()->routeIs('groups.*')" wire:navigate>
                        {{ __('Grupy') }}
                    </flux:sidebar.item>
                    @if ($showInvites)
                        <flux:sidebar.item icon="qr-code" :href="route('invites.index')" :current="request()->routeIs('invites.*')" wire:navigate>
                            {{ __('Zaproszenia') }}
                        </flux:sidebar.item>
                    @endif
                </x-app.nav-section>
            @endif

            @if ($schoolAdmin)
                <x-app.nav-section key="school-admin" :heading="__('Szkoła :name', ['name' => $school->short_name])" icon="building-library" :open="false"
                                   :active="request()->routeIs('admin.*')">
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
                        @feature('public.tv')
                            <flux:sidebar.item icon="tv" :href="route('admin.school.tv')" :current="request()->routeIs('admin.school.tv')" wire:navigate>
                                {{ __('Ekrany TV') }}
                            </flux:sidebar.item>
                        @endfeature
                    @endcan
                    @can('categories.manage')
                        <flux:sidebar.item icon="tag" :href="route('admin.school.catalog')" :current="request()->routeIs('admin.school.catalog')" wire:navigate>
                            {{ __('Kategorie i typy') }}
                        </flux:sidebar.item>
                    @endcan
                    @if ($user->can('workflows.manage') && $showWorkflows)
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('admin.workflows')" :current="request()->routeIs('admin.workflows*')" wire:navigate>
                            {{ __('Szablony obiegów') }}
                        </flux:sidebar.item>
                    @endif
                    @can('audit.view')
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>
                            {{ __('Logi szkoły') }}
                        </flux:sidebar.item>
                    @endcan
                </x-app.nav-section>
            @endif

            @can('site-admin')
                <x-app.nav-section key="site-admin" :heading="__('Witryna')" icon="globe-europe-africa" :open="false" :active="request()->routeIs('site.*')">
                    <flux:sidebar.item icon="building-office-2" :href="route('site.schools')" :current="request()->routeIs('site.schools*')" wire:navigate>
                        {{ __('Szkoły') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="swatch" :href="route('site.appearance')" :current="request()->routeIs('site.appearance')" wire:navigate>
                        {{ __('Wygląd') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('site.features')" :current="request()->routeIs('site.features')" wire:navigate>
                        {{ __('Funkcje') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="tag" :href="route('site.catalog')" :current="request()->routeIs('site.catalog')" wire:navigate>
                        {{ __('Kategorie i typy') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('site.workflows')" :current="request()->routeIs('site.workflows*')" wire:navigate>
                        {{ __('Szablony obiegów') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="tv" :href="route('site.tv')" :current="request()->routeIs('site.tv')" wire:navigate>
                        {{ __('Ekrany TV') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="beaker" :href="route('site.demo')" :current="request()->routeIs('site.demo')" wire:navigate>
                        {{ __('Tryb demo') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="book-open" :href="route('site.journal')" :current="request()->routeIs('site.journal')" wire:navigate>
                        {{ __('Dziennik witryny') }}
                    </flux:sidebar.item>
                </x-app.nav-section>
            @endcan
        </nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            @feature('public.front')
                <flux:sidebar.item icon="globe-alt" :href="route('home', ['podglad' => 1])">{{ __('Portal publiczny') }}</flux:sidebar.item>
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
        {{-- Inside main: the body is a grid (sidebar | header/main), a top-level bar would take a column on phones. --}}
        @include('partials.demo-banner', ['class' => '-mx-6 -mt-6 mb-6 lg:-mx-8 lg:-mt-8'])
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" class="mb-6" />
        @endif
        {{-- Comfortable reading width and outer margins on wide screens. --}}
        <div class="mx-auto w-full max-w-[112rem] xl:px-4 2xl:px-10">
            {{ $slot }}
        </div>
    </flux:main>

    @persist('toast')
        <flux:toast />
    @endpersist

    @fluxScripts
</body>
</html>
