<div class="space-y-6">
    <x-ui.page-header :title="__('Profil publiczny szkoły')" :subtitle="__('To widzą goście na stronie głównej portalu — bez logowania.')">
        <x-slot:actions>
            <flux:button :href="route('public.school', $school->slug)" variant="ghost" icon="eye">{{ __('Zobacz stronę') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        <form wire:submit="save">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Opis i kontakt') }}</flux:heading>
                <flux:input wire:model="tagline" :label="__('Hasło / krótki opis')" :placeholder="__('np. Technikum z tradycją od 1945 roku')" maxlength="200" />
                <flux:textarea wire:model="description" :label="__('O szkole')" rows="6" />
                <flux:input wire:model="address" :label="__('Adres')" icon="map-pin" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="phone" :label="__('Telefon')" icon="phone" />
                    <flux:input wire:model="public_email" type="email" :label="__('E-mail sekretariatu')" icon="envelope" />
                </div>
                <flux:input wire:model="website" type="url" :label="__('Strona internetowa')" icon="globe-alt" placeholder="https://" />
                <flux:switch wire:model="is_listed" :label="__('Pokazuj szkołę na stronie publicznej')" />
                <flux:text size="sm">{{ __('Na stronie publicznej nie pojawiają się nigdy dane pracowników ani wewnętrzne informacje szkoły.') }}</flux:text>
                <flux:button type="submit" variant="primary">{{ __('Zapisz') }}</flux:button>
            </flux:card>
        </form>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Oferta edukacyjna') }}</flux:heading>

            <div class="space-y-2">
                @forelse ($this->offers as $offer)
                    <div wire:key="o-{{ $offer->id }}" @class(['flex items-start gap-3 rounded-mci border border-zinc-200 p-3 dark:border-zinc-700', 'opacity-50' => ! $offer->is_active])>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs text-zinc-500">{{ $offer->kind->label() }}</div>
                            <div class="font-medium">{{ $offer->name }}</div>
                            @if ($offer->description)
                                <div class="text-sm text-zinc-500">{{ $offer->description }}</div>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center">
                            <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveOffer({{ $offer->id }}, -1)" :aria-label="__('W górę')" />
                            <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveOffer({{ $offer->id }}, 1)" :aria-label="__('W dół')" />
                            <flux:button size="xs" variant="ghost" :icon="$offer->is_active ? 'eye' : 'eye-slash'" wire:click="toggleOffer({{ $offer->id }})" :aria-label="__('Pokaż / ukryj')" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteOffer({{ $offer->id }})" wire:confirm="{{ __('Usunąć ten kierunek?') }}" :aria-label="__('Usuń')" />
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('Dodaj kierunki i formy kształcenia, które szkoła oferuje.') }}</flux:text>
                @endforelse
            </div>

            <form wire:submit="addOffer" class="space-y-3 rounded-mci bg-zinc-50 p-3 dark:bg-zinc-800">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($kinds as $kind)
                        <x-ui.pill wire:click="$set('offerKind', '{{ $kind->value }}')" :active="$offerKind === $kind->value">{{ $kind->label() }}</x-ui.pill>
                    @endforeach
                </div>
                <flux:input wire:model="offerName" :placeholder="__('np. Technik mechatronik')" />
                <flux:input wire:model="offerDescription" :placeholder="__('Krótki opis (opcjonalnie)')" />
                <flux:button type="submit" size="sm" icon="plus">{{ __('Dodaj kierunek') }}</flux:button>
            </form>
        </flux:card>
    </div>
</div>
