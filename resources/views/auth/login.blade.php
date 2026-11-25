<x-layouts::guest :title="__('Logowanie')">
    <div>
        <flux:heading size="lg">{{ __('Zaloguj się') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Użyj adresu e-mail podanego przez administratora.') }}</flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <flux:input name="email" type="email" :label="__('E-mail')" :value="old('email')" required autofocus autocomplete="email" />
        <flux:input name="password" type="password" :label="__('Hasło')" required autocomplete="current-password" viewable />

        <div class="flex items-center justify-between">
            <flux:checkbox name="remember" :label="__('Zapamiętaj mnie')" />
            <flux:link :href="route('password.request')" class="text-sm">{{ __('Nie pamiętasz hasła?') }}</flux:link>
        </div>

        <flux:button type="submit" variant="primary" class="w-full">{{ __('Zaloguj się') }}</flux:button>
    </form>
</x-layouts::guest>
