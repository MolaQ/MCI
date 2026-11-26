<?php

namespace App\Livewire\Site;

use App\Support\DemoMode as Demo;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Witryna → Tryb demo: switch the whole site to the separate demo database
 * and back, or rebuild it. Every action needs the admin's password and
 * typing DEMO; production data is never touched.
 */
#[Title('Tryb demo')]
class DemoMode extends Component
{
    public string $password = '';

    public string $confirmation = '';

    public function mount(): void
    {
        $this->authorize('site-admin');
    }

    private function confirmed(): bool
    {
        $this->resetErrorBag();
        if (! Hash::check($this->password, (string) Auth::user()->password)) {
            $this->addError('password', __('Nieprawidłowe hasło.'));

            return false;
        }
        if (mb_strtoupper(trim($this->confirmation)) !== 'DEMO') {
            $this->addError('confirmation', __('Wpisz DEMO, aby potwierdzić.'));

            return false;
        }

        return true;
    }

    public function enable()
    {
        $this->authorize('site-admin');
        if (Demo::isOn() || ! $this->confirmed()) {
            return null;
        }
        try {
            Demo::enable(Auth::user());
        } catch (\RuntimeException $e) {
            $this->addError('demo', $e->getMessage());

            return null;
        }

        // The demo database has its own accounts: start a fresh session there.
        Auth::guard('web')->logout();
        session()->invalidate();

        return redirect()->route('login')->with('status', __('Włączono tryb demo. Zaloguj się kontem demo.'));
    }

    public function disable()
    {
        $this->authorize('site-admin');
        if (! Demo::isOn() || ! $this->confirmed()) {
            return null;
        }
        Demo::disable(Auth::user());
        Auth::guard('web')->logout();
        session()->invalidate();

        return redirect()->route('login')->with('status', __('Wyłączono tryb demo. Strona znów używa danych produkcyjnych.'));
    }

    public function rebuild(): void
    {
        $this->authorize('site-admin');
        if (! $this->confirmed()) {
            return;
        }
        if (Demo::isOn()) {
            $this->addError('demo', __('Wyłącz tryb demo przed odtworzeniem bazy demo.'));

            return;
        }
        try {
            Demo::reset(Auth::user());
        } catch (\RuntimeException $e) {
            $this->addError('demo', $e->getMessage());

            return;
        }
        $this->reset('password', 'confirmation');
        Flux::toast(variant: 'success', text: __('Odtworzono dane demonstracyjne.'));
    }

    public function render()
    {
        $safe = null;
        try {
            Demo::assertSafe();
        } catch (\RuntimeException $e) {
            $safe = $e->getMessage();
        }

        return view('livewire.site.demo-mode', [
            'on' => Demo::isOn(),
            'state' => Demo::state(),
            'ready' => Demo::isReady(),
            'demoDatabase' => basename(Demo::databaseName()),
            'mainDatabase' => basename(Demo::mainDatabaseName()),
            'unsafe' => $safe,
        ]);
    }
}
