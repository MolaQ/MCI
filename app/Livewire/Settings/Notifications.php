<?php

namespace App\Livewire\Settings;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\TestPushNotification;
use App\Support\Audit;
use App\Support\Push;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ustawienia → Powiadomienia i aplikacja: install the PWA, turn Web Push
 * on for this device, see/remove devices, choose what may ring the phone,
 * and send a test. Browser-side steps live in resources/js/push.js.
 */
#[Title('Powiadomienia i aplikacja')]
class Notifications extends Component
{
    /** @var array<string, bool> */
    public array $prefs = [];

    public function mount(): void
    {
        foreach (User::PUSH_KINDS as $kind => $default) {
            $this->prefs[$kind] = Auth::user()->wantsPush($kind);
        }
    }

    #[Computed]
    public function devices()
    {
        return Auth::user()->pushSubscriptions()->latest('updated_at')->get();
    }

    public function updatedPrefs(): void
    {
        $user = Auth::user();
        $clean = array_map('boolval', array_intersect_key($this->prefs, User::PUSH_KINDS));
        $prefs = $user->notification_prefs ?? [];
        $prefs['push'] = $clean;
        $user->forceFill(['notification_prefs' => $prefs])->save();

        Flux::toast(text: __('Zapisano ustawienia powiadomień.'));
    }

    /**
     * @param  array{endpoint?: string, keys?: array{p256dh?: string, auth?: string}}  $subscription
     */
    public function subscribe(array $subscription, string $label = '', string $platform = ''): void
    {
        abort_unless(Push::enabled(), 403);

        $data = Validator::make(['s' => $subscription, 'label' => $label, 'platform' => $platform], [
            's.endpoint' => ['required', 'string', 'url', 'max:1024', 'starts_with:https://'],
            's.keys.p256dh' => ['required', 'string', 'max:255'],
            's.keys.auth' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'in:ios,android,desktop'],
        ])->validate();

        $user = Auth::user();
        // An endpoint belongs to one browser; if someone else used this device before, it moves to the current user.
        PushSubscription::query()->where('endpoint', $data['s']['endpoint'])
            ->where(fn ($q) => $q->where('subscribable_type', '!=', $user->getMorphClass())->orWhere('subscribable_id', '!=', $user->id))
            ->delete();

        $sub = $user->updatePushSubscription($data['s']['endpoint'], $data['s']['keys']['p256dh'], $data['s']['keys']['auth'], 'aes128gcm');
        $sub->forceFill(['device_label' => $data['label'] ?: null, 'platform' => $data['platform'] ?: null, 'failed_at' => null])->save();

        Audit::log('push.subscribed', $user, ['device' => $sub->device_label, 'platform' => $sub->platform], $user);
        unset($this->devices);
        Flux::toast(variant: 'success', text: __('Powiadomienia włączone na tym urządzeniu.'));
    }

    public function unsubscribeEndpoint(string $endpoint): void
    {
        Auth::user()->deletePushSubscription($endpoint);
        unset($this->devices);
    }

    public function removeDevice(int $id): void
    {
        $sub = Auth::user()->pushSubscriptions()->whereKey($id)->firstOrFail();
        $sub->delete();
        Audit::log('push.device_removed', Auth::user(), ['device' => $sub->device_label], Auth::user());
        unset($this->devices);
        Flux::toast(text: __('Usunięto urządzenie.'));
    }

    public function sendTest(): void
    {
        abort_unless(Push::enabled(), 403);
        $user = Auth::user();

        if ($user->pushSubscriptions()->doesntExist()) {
            Flux::toast(variant: 'warning', text: __('Najpierw włącz powiadomienia na tym urządzeniu.'));

            return;
        }

        $user->notify(new TestPushNotification);
        unset($this->devices);
        Flux::toast(text: __('Wysłano powiadomienie testowe. Powinno pojawić się w ciągu kilku sekund.'));
    }

    public function render()
    {
        return view('livewire.settings.notifications', [
            'pushConfigured' => Push::configured(),
            'pushEnabled' => Push::enabled(),
            'vapidKey' => Push::publicKey(),
            'kinds' => [
                'mentions' => [__('Wzmianki o mnie'), __('Ktoś napisał @Twoje imię.')],
                'important' => [__('Ważne i pilne komunikaty'), __('Priorytet „ważny” lub „pilny”.')],
                'confirmations' => [__('Prośby o potwierdzenie'), __('Komunikaty wymagające potwierdzenia zapoznania się.')],
                'tasks' => [__('Zadania'), __('Nowe przydziały, przypomnienia o terminach i wykonanie Twoich zadań.')],
                'workflows' => [__('Obiegi i wnioski'), __('Wnioski czekające na Twoją decyzję, zwrócone do poprawy i decyzje w Twoich wnioskach.')],
                'announcements' => [__('Wszystkie komunikaty'), __('Także zwykłe komunikaty — może być ich sporo.')],
            ],
        ]);
    }
}
