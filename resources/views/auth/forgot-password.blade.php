<x-layouts::guest :title="__('Przypomnienie hasła')">
    <div>
        <flux:heading size="lg">{{ __('Nie pamiętasz hasła?') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Podaj swój e-mail, a wyślemy link do ustawienia nowego hasła.') }}</flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <flux:input name="email" type="email" :label="__('E-mail')" :value="old('email')" required autofocus />
        <flux:button type="submit" variant="primary" class="w-full">{{ __('Wyślij link') }}</flux:button>
    </form>

    <flux:link :href="route('login')" class="text-sm">{{ __('Wróć do logowania') }}</flux:link>
</x-layouts::guest>
