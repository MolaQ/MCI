@props(['user', 'compact' => false])

<flux:dropdown position="top" align="{{ $compact ? 'end' : 'start' }}" {{ $attributes }}>
    @if ($compact)
        <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />
    @else
        <flux:sidebar.profile :name="$user->name" :initials="$user->initials()" icon-trailing="chevron-up-down" />
    @endif

    <flux:menu class="min-w-60">
        <div class="px-2 py-1.5">
            <div class="truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $user->name }}</div>
            <div class="truncate text-xs text-zinc-500">{{ $user->email }}</div>
        </div>
        <flux:menu.separator />
        <flux:menu.item icon="user-circle" :href="route('profile')" wire:navigate>{{ __('Mój profil') }}</flux:menu.item>
        <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">{{ __('Motyw jasny') }}</flux:menu.item>
        <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">{{ __('Motyw ciemny') }}</flux:menu.item>
        <flux:menu.item icon="computer-desktop" x-on:click="$flux.appearance = 'system'">{{ __('Motyw systemowy') }}</flux:menu.item>
        <flux:menu.separator />
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                {{ __('Wyloguj się') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
