<div class="space-y-6">
    <x-ui.page-header :title="__('Mój profil')" :subtitle="$user->email" />

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('Dane') }}</flux:heading>
            <dl class="grid grid-cols-3 gap-y-2 text-sm">
                <dt class="text-zinc-500">{{ __('Imię i nazwisko') }}</dt><dd class="col-span-2">{{ $user->name }}</dd>
                <dt class="text-zinc-500">{{ __('Szkoły') }}</dt><dd class="col-span-2">{{ $user->schools->pluck('short_name')->join(', ') ?: '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Role (ta szkoła)') }}</dt>
                <dd class="col-span-2">{{ $user->roles->map(fn ($r) => \App\Enums\Role::tryFrom($r->name)?->label() ?? $r->name)->join(', ') ?: '—' }}</dd>
            </dl>
            <form wire:submit="saveProfile" class="flex items-end gap-2">
                <flux:input wire:model="phone" :label="__('Telefon')" class="flex-1" />
                <flux:button type="submit">{{ __('Zapisz') }}</flux:button>
            </form>
            <flux:text size="sm">{{ __('Imię, nazwisko i e-mail zmienia administrator.') }}</flux:text>
        </flux:card>

        <flux:card class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Prywatność profilu') }}</flux:heading>
                <flux:text>{{ __('Ty decydujesz, kto poza Twoimi szkołami widzi Twój profil. Szkoła może ustawić górny limit.') }}</flux:text>
            </div>
            <form wire:submit="savePrivacy" class="space-y-4">
                <flux:radio.group wire:model="profile_visibility" variant="cards" class="flex-col">
                    @foreach ($visibilities as $v)
                        <flux:radio :value="$v->value" :label="$v->label()" :description="$v->description()" />
                    @endforeach
                </flux:radio.group>
                <flux:checkbox.group wire:model="profile_fields" :label="__('Pokazuj oprócz imienia i nazwiska')">
                    @foreach ($fields as $key => $label)
                        <flux:checkbox :value="$key" :label="$label" />
                    @endforeach
                </flux:checkbox.group>
                @if ($effective !== $user->profile_visibility)
                    <flux:callout variant="warning" icon="shield-check"
                        :heading="__('Obecnie obowiązuje: :level', ['level' => $effective->label()])"
                        :text="__('Zasady jednej z Twoich szkół ograniczają widoczność profilu.')" />
                @endif
                <flux:button type="submit" variant="primary">{{ __('Zapisz prywatność') }}</flux:button>
            </form>
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

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Moje grupy') }}</flux:heading>
            <div class="space-y-2">
                @forelse ($groups as $group)
                    <div class="flex items-center gap-2">
                        <x-app.school-badge :school="$group->school" size="sm" />
                        <x-app.group-chip :group="$group" />
                    </div>
                @empty
                    <flux:text>{{ __('Brak') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
