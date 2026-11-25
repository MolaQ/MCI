<div class="space-y-6">
    <x-ui.page-header :title="$user ? $user->name : __('Nowy użytkownik')"
                      :subtitle="$user ? __('Utworzono :date', ['date' => $user->created_at->translatedFormat('j F Y')]) : __('Konto zostanie utworzone ze statusem „Oczekuje”, dopóki osoba nie ustawi hasła.')">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.users.index')" wire:navigate>{{ __('Wróć') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <flux:heading size="lg">{{ __('Dane osoby') }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="first_name" :label="__('Imię')" required />
                    <flux:input wire:model="last_name" :label="__('Nazwisko')" required />
                    <flux:input wire:model="email" type="email" :label="__('E-mail (login)')" required />
                    <flux:input wire:model="phone" :label="__('Telefon')" :description="__('Opcjonalnie, na potrzeby powiadomień awaryjnych.')" />
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Grupy') }}</flux:heading>
                        <flux:text>{{ __('Osoba może należeć do wielu grup. Lider zarządza członkami swojej grupy.') }}</flux:text>
                    </div>
                    <flux:input wire:model.live.debounce.250ms="groupFilter" size="sm" icon="magnifying-glass" :placeholder="__('Filtruj grupy')" class="max-w-56" />
                </div>

                <flux:error name="memberships" />

                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse ($this->groups as $group)
                        <div class="flex items-center justify-between gap-3 py-2" wire:key="g-{{ $group->id }}">
                            <div class="min-w-0">
                                <x-app.group-chip :group="$group" />
                                <div class="ms-4.5 text-xs text-zinc-500">{{ $group->type->label() }}</div>
                            </div>
                            <flux:radio.group wire:model="memberships.{{ $group->id }}" variant="segmented" size="sm">
                                <flux:radio value="" :label="__('Brak')" />
                                <flux:radio value="member" :label="__('Członek')" />
                                <flux:radio value="leader" :label="__('Lider')" />
                            </flux:radio.group>
                        </div>
                    @empty
                        <x-ui.empty-state icon="user-group" :title="__('Brak grup')">
                            @can('create', \App\Models\Group::class)
                                <flux:button size="sm" :href="route('groups.create')" wire:navigate>{{ __('Utwórz grupę') }}</flux:button>
                            @endcan
                        </x-ui.empty-state>
                    @endforelse
                </div>
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-5">
                <flux:heading size="lg">{{ __('Konto') }}</flux:heading>

                <flux:select wire:model="status" :label="__('Status')">
                    @foreach ($statuses as $s)
                        <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($this->canManageAccess)
                    <flux:checkbox.group wire:model="roles" :label="__('Role')">
                        @foreach ($allRoles as $r)
                            <flux:checkbox :value="$r->value" :label="$r->label()" />
                        @endforeach
                    </flux:checkbox.group>
                @else
                    <div>
                        <flux:label>{{ __('Role') }}</flux:label>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($roles as $r)
                                <flux:badge size="sm">{{ \App\Enums\Role::tryFrom($r)?->label() ?? $r }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! $user)
                    <flux:checkbox wire:model="sendSetupLink" :label="__('Wyślij e-mail z linkiem do ustawienia hasła')" />
                @endif

                <flux:button type="submit" variant="primary" class="w-full">{{ $user ? __('Zapisz zmiany') : __('Utwórz konto') }}</flux:button>
            </flux:card>

            @if ($this->canManageAccess)
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Uprawnienia indywidualne') }}</flux:heading>
                        <flux:text>{{ __('Dodatkowe uprawnienia ponad te wynikające z roli, np. „Wysyłanie alarmów” dla inspektora BHP.') }}</flux:text>
                    </div>
                    <flux:checkbox.group wire:model="permissions">
                        @foreach ($allPermissions as $p)
                            <flux:checkbox :value="$p->value" :label="$p->label()" :description="$p->isSensitive() ? __('Uprawnienie wrażliwe') : null" />
                        @endforeach
                    </flux:checkbox.group>
                </flux:card>
            @endif

            @if ($user)
                @can('delete', $user)
                    <flux:card class="space-y-3">
                        <flux:heading>{{ __('Usuń konto') }}</flux:heading>
                        <flux:text>{{ __('Konto zostanie dezaktywowane i usunięte z grup. Historia w dzienniku zdarzeń zostaje.') }}</flux:text>
                        <flux:button variant="danger" size="sm" wire:click="delete" wire:confirm="{{ __('Na pewno usunąć to konto?') }}">{{ __('Usuń konto') }}</flux:button>
                    </flux:card>
                @endcan
            @endif
        </div>
    </form>
</div>
