<div class="space-y-6">
    <x-ui.page-header :title="$group ? __('Edycja: :name', ['name' => $group->name]) : __('Nowa grupa')">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="$group ? route('groups.show', $group) : route('groups.index')" wire:navigate>{{ __('Wróć') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="max-w-2xl space-y-6">
        <flux:card class="space-y-5">
            <flux:input wire:model="name" :label="__('Nazwa')" required />
            <flux:textarea wire:model="description" :label="__('Opis')" rows="3" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="type" :label="__('Typ')" :disabled="! $canChangeStructure">
                    @foreach ($types as $t)
                        <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="membership_mode" :label="__('Dołączanie')" :disabled="! $canChangeStructure">
                    @foreach ($modes as $m)
                        <flux:select.option :value="$m->value">{{ $m->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="parent_id" :label="__('Grupa nadrzędna')" :disabled="! $canChangeStructure">
                    <flux:select.option value="">{{ __('Brak') }}</flux:select.option>
                    @foreach ($parents as $p)
                        <flux:select.option :value="$p->id">{{ $p->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:field>
                    <flux:label>{{ __('Kolor') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="color" class="h-10 w-14 cursor-pointer rounded-mci border border-zinc-200 bg-white p-1 dark:border-zinc-600 dark:bg-zinc-800" />
                        <flux:input wire:model.live="color" placeholder="#2563eb" class="max-w-32" />
                    </div>
                    <flux:error name="color" />
                </flux:field>
            </div>
        </flux:card>

        <div class="flex items-center justify-between">
            <flux:button type="submit" variant="primary">{{ __('Zapisz') }}</flux:button>
            @if ($group)
                @can('delete', $group)
                    <flux:button variant="danger" wire:click="delete" wire:confirm="{{ __('Usunąć grupę „:name”? Członkostwa zostaną usunięte.', ['name' => $group->name]) }}">{{ __('Usuń grupę') }}</flux:button>
                @endcan
            @endif
        </div>
    </form>
</div>
