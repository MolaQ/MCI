<x-layouts::guest :title="__('Potwierdź e-mail')">
    <div class="space-y-3 text-center">
        <flux:icon.envelope class="mx-auto size-10 text-zinc-400" />
        <flux:heading size="lg">{{ __('Sprawdź skrzynkę e-mail') }}</flux:heading>
        <flux:text>{{ __('Wysłaliśmy link potwierdzający na :email. Kliknij go, a potem zaloguj się.', ['email' => session('email', __('Twój adres'))]) }}</flux:text>
        <flux:text size="sm">{{ __('Nie widzisz wiadomości? Sprawdź folder SPAM.') }}</flux:text>
    </div>
    <flux:button :href="route('login')" class="w-full">{{ __('Przejdź do logowania') }}</flux:button>
</x-layouts::guest>
