@if (\App\Support\DemoMode::isOn())
    <div class="{{ $class ?? '' }} relative z-40 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-400 px-4 py-1.5 text-center text-xs font-medium text-amber-950" role="status" data-demo-banner>
        <span class="inline-flex items-center gap-1.5"><flux:icon name="beaker" variant="micro" /> {{ __('TRYB DEMO: przykładowe dane w osobnej bazie, zmiany nie trafiają do systemu produkcyjnego.') }}</span>
        @guest
            <span>{{ __('Konto demo: :email / :password', ['email' => config('mci.demo.admin_email'), 'password' => config('mci.demo.admin_password')]) }}</span>
        @endguest
    </div>
@endif
