<div class="space-y-6">
    <x-ui.page-header :title="__('Mój profil')" :subtitle="$user->email" />

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('Dane') }}</flux:heading>
            <dl class="grid grid-cols-3 gap-y-2 text-sm">
                <dt class="text-zinc-500">{{ __('Imię i nazwisko') }}</dt><dd class="col-span-2">{{ $user->name }}</dd>
                <dt class="text-zinc-500">{{ __('Role') }}</dt>
                <dd class="col-span-2">{{ $user->roles->map(fn ($r) => \App\Enums\Role::tryFrom($r->name)?->label() ?? $r->name)->join(', ') ?: '—' }}</dd>
            </dl>
            <form wire:submit="saveProfile" class="flex items-end gap-2">
                <flux:input wire:model="phone" :label="__('Telefon')" class="flex-1" />
                <flux:button type="submit">{{ __('Zapisz') }}</flux:button>
            </form>
            <flux:text size="sm">{{ __('Imię, nazwisko i e-mail zmienia administrator.') }}</flux:text>
        </flux:card>

        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('Zmiana hasła') }}</flux:heading>
            <form wire:submit="updatePassword" class="space-y-4">
                <flux:input wire:model="current_password" type="password" :label="__('Obecne hasło')" autocomplete="current-password" viewable />
                <flux:input wire:model="password" type="password" :label="__('Nowe hasło')" autocomplete="new-password" viewable />
                <flux:input wire:model="password_confirmation" type="password" :label="__('Powtórz nowe hasło')" autocomplete="new-password" viewable />
                <flux:button type="submit" variant="primary">{{ __('Zmień hasło') }}</flux:button>
            </form>
        </flux:card>

        <flux:card class="space-y-3 lg:col-span-2">
            <flux:heading size="lg">{{ __('Moje grupy') }}</flux:heading>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @forelse ($user->groups as $group)
                    <a href="{{ route('groups.show', $group) }}" wire:navigate><x-app.group-chip :group="$group" /></a>
                @empty
                    <flux:text>{{ __('Brak') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
