<x-layouts::guest :title="__('Ustaw hasło')">
    <div>
        <flux:heading size="lg">{{ __('Ustaw hasło') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Wybierz hasło, którym będziesz logować się do systemu.') }}</flux:text>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <flux:input name="email" type="email" :label="__('E-mail')" :value="old('email', $request->email)" required readonly />
        <flux:input name="password" type="password" :label="__('Nowe hasło')" required autofocus autocomplete="new-password" viewable />
        <flux:input name="password_confirmation" type="password" :label="__('Powtórz hasło')" required autocomplete="new-password" viewable />
        <flux:button type="submit" variant="primary" class="w-full">{{ __('Zapisz hasło') }}</flux:button>
    </form>
</x-layouts::guest>
