<div class="space-y-6">
    <x-ui.page-header :title="__('Użytkownicy')" :subtitle="__('Konta pracowników i (opcjonalnie) uczniów, ich role i grupy.')">
        <x-slot:actions>
            <flux:button icon="arrow-up-tray" :href="route('admin.users.import')" wire:navigate>{{ __('Import CSV') }}</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('admin.users.create')" wire:navigate>{{ __('Nowy użytkownik') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Szukaj: imię, nazwisko, e-mail')" class="lg:col-span-2" />
        <flux:select wire:model.live="role">
            <flux:select.option value="">{{ __('Wszystkie role') }}</flux:select.option>
            @foreach ($roles as $r)
                <flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status">
            <flux:select.option value="">{{ __('Każdy status') }}</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="group">
            <flux:select.option value="">{{ __('Wszystkie grupy') }}</flux:select.option>
            @foreach ($this->groups as $g)
                <flux:select.option :value="$g->id">{{ $g->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="flex items-center gap-4">
        <flux:switch wire:model.live="withoutGroups" :label="__('Tylko bez grupy')" align="left" />
        <flux:button size="sm" variant="ghost" wire:click="resetFilters">{{ __('Wyczyść filtry') }}</flux:button>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'last_name'" :direction="$sortDirection" wire:click="sort('last_name')">{{ __('Osoba') }}</flux:table.column>
            <flux:table.column>{{ __('Rola') }}</flux:table.column>
            <flux:table.column>{{ __('Grupy') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'last_seen_at'" :direction="$sortDirection" wire:click="sort('last_seen_at')" class="max-md:hidden">{{ __('Ostatnio') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="flex items-center gap-3">
                            <flux:avatar size="sm" :initials="$user->initials()" />
                            <div class="min-w-0">
                                <div class="truncate font-medium text-zinc-800 dark:text-white">{{ $user->last_name }} {{ $user->first_name }}</div>
                                <div class="truncate text-xs text-zinc-500">{{ $user->email }}</div>
                            </div>
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        @foreach ($user->roles as $r)
                            <flux:badge size="sm">{{ \App\Enums\Role::tryFrom($r->name)?->label() ?? $r->name }}</flux:badge>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell class="max-w-64">
                        <div class="flex flex-wrap gap-x-3 gap-y-1 whitespace-normal text-xs">
                            @foreach ($user->groups->take(4) as $g)
                                <x-app.group-chip :group="$g" />
                            @endforeach
                            @if ($user->groups->count() > 4)
                                <span class="text-zinc-500">+{{ $user->groups->count() - 4 }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$user->status->color()" inset="top bottom">{{ $user->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden text-zinc-500">
                        {{ $user->last_seen_at?->diffForHumans() ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Akcje')" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" :href="route('admin.users.edit', $user)" wire:navigate>{{ __('Edytuj') }}</flux:menu.item>
                                @can('update', $user)
                                    <flux:menu.item icon="envelope" wire:click="sendSetupLink({{ $user->id }})">{{ __('Wyślij link do hasła') }}</flux:menu.item>
                                @endcan
                                @can('suspend', $user)
                                    <flux:menu.item icon="no-symbol" variant="danger" wire:click="toggleSuspension({{ $user->id }})"
                                                    wire:confirm="{{ $user->status === \App\Enums\UserStatus::Suspended ? __('Przywrócić konto?') : __('Zawiesić konto? Użytkownik zostanie wylogowany.') }}">
                                        {{ $user->status === \App\Enums\UserStatus::Suspended ? __('Przywróć konto') : __('Zawieś konto') }}
                                    </flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <x-ui.empty-state icon="users" :title="__('Brak użytkowników spełniających kryteria')" />
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
