<div class="space-y-6">
    <x-ui.page-header :title="$school ? $school->name : __('Nowa szkoła')"
                      :subtitle="$siteAdmin ? __('Dane szkoły i jej zasady.') : __('Zasady obowiązujące w Twojej szkole.')">
        @if ($siteAdmin)
            <x-slot:actions>
                <flux:button variant="ghost" icon="arrow-left" :href="route('site.schools')" wire:navigate>{{ __('Szkoły') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form wire:submit="save" class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('Dane') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Pełna nazwa')" :disabled="! $siteAdmin" />
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="short_name" :label="__('Skrót')" :disabled="! $siteAdmin" />
                <flux:input wire:model="slug" :label="__('Adres (slug)')" :disabled="! $siteAdmin" :description="__('Np. w publicznym kalendarzu.')" />
            </div>
            <flux:input wire:model="city" :label="__('Miejscowość')" :disabled="! $siteAdmin" />
            @if ($siteAdmin)
                <x-ui.image-editor model="logoUpload" :label="__('Logo szkoły')" :current="$school?->logoUrl()" :remove="$school ? 'removeLogo' : null"
                                   :presets="['free', '1:1', '3:1', '4:1']" :max-size="1024" />
                <flux:switch wire:model="is_active" :label="__('Szkoła aktywna')" :description="__('Nieaktywna szkoła znika z przełącznika, a jej zaproszenia przestają działać.')" />
            @endif
        </flux:card>

        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('Zasady szkoły') }}</flux:heading>
            <flux:input wire:model="email_domains" :label="__('Domeny e-mail szkoły')" placeholder="zsm.edu.pl, uczen.zsm.edu.pl"
                        :description="__('Samodzielna rejestracja z kodu QR tylko dla adresów w tych domenach. Puste = dowolna domena.')" />
            <flux:switch wire:model="allow_self_registration" :label="__('Pozwalaj na zakładanie kont z kodów QR grup')" />
            <flux:radio.group wire:model="max_profile_visibility" :label="__('Maksymalna widoczność profili')" variant="cards" class="flex-col">
                @foreach ($visibilities as $v)
                    <flux:radio :value="$v->value" :label="$v->label()" :description="$v->description()" />
                @endforeach
            </flux:radio.group>
            <flux:text size="sm">{{ __('Każda osoba sama wybiera widoczność swojego profilu; ta wartość jest górnym limitem dla wszystkich członków szkoły.') }}</flux:text>
        </flux:card>

        <div class="lg:col-span-2">
            <flux:button type="submit" variant="primary">{{ __('Zapisz') }}</flux:button>
        </div>
    </form>
</div>
