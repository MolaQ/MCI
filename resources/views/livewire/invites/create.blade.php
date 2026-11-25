<div class="space-y-6">
    <x-ui.page-header :title="__('Nowe zaproszenie')" :subtitle="__('Jedno zaproszenie może dodać osobę do kilku grup naraz.')">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('invites.index')" wire:navigate>{{ __('Wróć') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <flux:radio.group wire:model.live="kind" variant="cards" class="max-sm:flex-col">
                    <flux:radio value="group_link" icon="qr-code" :label="__('Link / kod QR')" :description="__('Wielokrotny — np. plakat w pokoju nauczycielskim.')" />
                    <flux:radio value="personal" icon="envelope" :label="__('Imienne')" :description="__('Dla jednej osoby, wysyłane e-mailem, jednorazowe.')" />
                </flux:radio.group>

                @if ($kind === 'personal')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="email" type="email" :label="__('E-mail osoby')" required />
                        <div class="pt-7"><flux:checkbox wire:model="send_email" :label="__('Wyślij zaproszenie e-mailem')" /></div>
                    </div>
                @endif

                <flux:input wire:model="label" :label="__('Nazwa (opcjonalnie)')" :placeholder="__('np. Plakat — pokój nauczycielski')" />
            </flux:card>

            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Grupy') }}</flux:heading>
                    <flux:text>{{ __('Kliknij, aby zaznaczyć. Gwiazdka nadaje rolę lidera (jeśli masz do tego uprawnienie).') }}</flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    @forelse ($this->invitableGroups as $group)
                        @php($selected = isset($groups[$group->id]))
                        <span wire:key="pg-{{ $group->id }}" class="inline-flex items-center">
                            <x-ui.pill :active="$selected" wire:click="toggleGroup({{ $group->id }})" @class(['rounded-e-none' => $selected && $group->can_lead])>
                                <span class="size-2 rounded-full" style="background-color: {{ $group->displayColor() }}"></span>
                                {{ $group->name }}
                            </x-ui.pill>
                            @if ($selected && $group->can_lead)
                                <button type="button" wire:click="toggleLeader({{ $group->id }})" title="{{ __('Lider') }}"
                                    @class([
                                        'rounded-e-full border border-s-0 px-2 py-1 text-sm transition-colors',
                                        'border-primary bg-amber-400 text-zinc-900' => ($groups[$group->id] ?? '') === 'leader',
                                        'border-primary bg-primary/80 text-primary-foreground' => ($groups[$group->id] ?? '') !== 'leader',
                                    ])>
                                    <flux:icon.star variant="micro" />
                                </button>
                            @endif
                        </span>
                    @empty
                        <flux:text>{{ __('Nie masz grup, do których możesz zapraszać.') }}</flux:text>
                    @endforelse
                </div>
                <flux:error name="groups" />
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-5">
                <div>
                    <flux:label>{{ __('Ważne przez') }}</flux:label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (\App\Livewire\Invites\Create::DAY_PRESETS as $d)
                            <x-ui.pill :active="$days === $d" wire:click="$set('days', {{ $d }})">{{ trans_choice(':count dzień|:count dni|:count dni', $d) }}</x-ui.pill>
                        @endforeach
                    </div>
                    <flux:error name="days" />
                </div>

                @if ($kind === 'group_link')
                    <div>
                        <flux:label>{{ __('Limit użyć') }}</flux:label>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <x-ui.pill :active="$max_uses === null" wire:click="$set('max_uses', null)">{{ __('Bez limitu') }}</x-ui.pill>
                            @foreach (\App\Livewire\Invites\Create::USE_PRESETS as $n)
                                <x-ui.pill :active="$max_uses === $n" wire:click="$set('max_uses', {{ $n }})">{{ $n }}</x-ui.pill>
                            @endforeach
                        </div>
                    </div>

                    <flux:switch wire:model="allow_self_registration" :label="__('Pozwól założyć nowe konto')"
                        :description="__('Osoby bez konta mogą je utworzyć (adres w domenie szkoły, potwierdzenie e-mailem).')" />
                @endif

                @if ($this->canGrantRoles)
                    <flux:select wire:model="role_name" :label="__('Nadaj rolę w szkole (opcjonalnie)')">
                        <flux:select.option value="">{{ __('Bez zmiany roli') }}</flux:select.option>
                        @foreach ($roles as $r)
                            @if ($r !== \App\Enums\Role::Admin || $kind === 'personal')
                                <flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>
                @endif

                <flux:button type="submit" variant="primary" class="w-full" icon="qr-code">{{ __('Utwórz zaproszenie') }}</flux:button>
            </flux:card>
        </div>
    </form>
</div>
