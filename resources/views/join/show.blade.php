<x-layouts::guest :title="__('Zaproszenie')">
    <div class="space-y-1">
        <flux:text size="sm" class="uppercase tracking-wide">{{ __('Zaproszenie') }}</flux:text>
        <div class="flex items-center gap-3">
            <x-app.school-badge :school="$invite->school" />
            <flux:heading size="lg">{{ $invite->school->name }}</flux:heading>
        </div>
    </div>

    <div class="space-y-2">
        @if ($invite->groups->isNotEmpty())
            <flux:text>{{ __('Dołączysz do grup:') }}</flux:text>
            <ul class="space-y-1.5">
                @foreach ($invite->groups as $group)
                    <li class="flex items-center justify-between gap-2">
                        <x-app.group-chip :group="$group" />
                        @if ($group->pivot->group_role === 'leader')
                            <flux:badge size="sm" color="amber">{{ __('Lider') }}</flux:badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($invite->role_name)
            <flux:text>{{ __('Rola w szkole:') }} <strong>{{ \App\Enums\Role::from($invite->role_name)->label() }}</strong></flux:text>
        @endif
        @if ($invite->expires_at)
            <flux:text size="sm">{{ __('Ważne do :date', ['date' => $invite->expires_at->format('d.m.Y H:i')]) }}</flux:text>
        @endif
    </div>

    @error('invite')
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
    @enderror

    @if ($user)
        @if ($emailMismatch)
            <flux:callout variant="warning" icon="exclamation-triangle"
                :heading="__('To zaproszenie jest przypisane do innego adresu e-mail.')"
                :text="__('Jesteś zalogowany(-a) jako :email. Wyloguj się i otwórz link ponownie.', ['email' => $user->email])" />
        @elseif ($alreadyMember)
            <flux:callout variant="success" icon="check-circle" :heading="__('Już należysz do tej szkoły i wszystkich tych grup.')" />
            <flux:button :href="route('dashboard')" class="w-full">{{ __('Przejdź do pulpitu') }}</flux:button>
        @else
            <form method="POST" action="{{ route('join.accept', $token) }}" class="space-y-3">
                @csrf
                <flux:text size="sm">{{ __('Zalogowano jako :name (:email).', ['name' => $user->name, 'email' => $user->email]) }}</flux:text>
                <flux:button type="submit" variant="primary" class="w-full">{{ __('Dołącz') }}</flux:button>
            </form>
        @endif
    @elseif ($needsActivation)
        <form method="POST" action="{{ route('join.activate', $token) }}" class="space-y-4">
            @csrf
            <flux:text>{{ __('Twoje konto (:email) czeka na aktywację. Ustaw hasło:', ['email' => $invite->email]) }}</flux:text>
            <flux:input name="password" type="password" :label="__('Hasło')" required autocomplete="new-password" viewable />
            <flux:input name="password_confirmation" type="password" :label="__('Powtórz hasło')" required autocomplete="new-password" viewable />
            <flux:button type="submit" variant="primary" class="w-full">{{ __('Aktywuj konto i dołącz') }}</flux:button>
        </form>
    @else
        <form method="POST" action="{{ route('join.login', $token) }}">
            @csrf
            <flux:button type="submit" :variant="$accountExists || ! $canRegister ? 'primary' : 'filled'" class="w-full" icon="arrow-right-end-on-rectangle">
                {{ __('Mam konto — zaloguj się') }}
            </flux:button>
        </form>

        @if ($canRegister && ! $accountExists)
            <flux:separator :text="__('lub utwórz konto')" />
            <form method="POST" action="{{ route('join.register', $token) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <flux:input name="first_name" :label="__('Imię')" :value="old('first_name')" required autocomplete="given-name" />
                    <flux:input name="last_name" :label="__('Nazwisko')" :value="old('last_name')" required autocomplete="family-name" />
                </div>
                @if ($invite->kind === \App\Enums\InviteKind::Personal)
                    <flux:input :label="__('E-mail')" :value="$invite->email" readonly />
                @else
                    <flux:input name="email" type="email" :label="__('E-mail')" :value="old('email')" required autocomplete="email"
                        :description="$invite->school->email_domains ? __('Tylko adresy w domenie: :d', ['d' => implode(', ', $invite->school->email_domains)]) : null" />
                @endif
                <flux:input name="password" type="password" :label="__('Hasło')" required autocomplete="new-password" viewable />
                <flux:input name="password_confirmation" type="password" :label="__('Powtórz hasło')" required autocomplete="new-password" viewable />
                <flux:checkbox name="accept" value="1" :label="__('Akceptuję zasady korzystania z :app i przetwarzanie moich danych przez szkołę.', ['app' => $appearance->shortName()])" />
                <flux:button type="submit" variant="primary" class="w-full">{{ __('Utwórz konto i dołącz') }}</flux:button>
                @if ($invite->kind === \App\Enums\InviteKind::GroupLink)
                    <flux:text size="sm">{{ __('Wyślemy e-mail z linkiem potwierdzającym. Logowanie będzie możliwe po potwierdzeniu.') }}</flux:text>
                @endif
            </form>
        @elseif (! $accountExists)
            <flux:text size="sm">{{ __('To zaproszenie jest tylko dla osób, które mają już konto w :app. Nie masz konta? Poproś administratora szkoły.', ['app' => $appearance->shortName()]) }}</flux:text>
        @endif
    @endif
</x-layouts::guest>
