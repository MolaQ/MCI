<x-layouts::guest :title="__('Zaproszenie nieważne')">
    <div class="space-y-3 text-center">
        <flux:icon.link-slash class="mx-auto size-10 text-zinc-400" />
        <flux:heading size="lg">{{ __('Zaproszenie jest nieważne lub wygasło') }}</flux:heading>
        <flux:text>{{ __('Poproś osobę, która je udostępniła, o nowy link lub kod QR.') }}</flux:text>
    </div>
    <flux:button :href="route('login')" class="w-full">{{ __('Przejdź do logowania') }}</flux:button>
</x-layouts::guest>
