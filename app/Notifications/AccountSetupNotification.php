<?php

namespace App\Notifications;

use App\Support\Appearance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSetupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token)
    {
        $this->onQueue('mail');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);
        $school = app(Appearance::class)->schoolName();
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('Twoje konto w :app', ['app' => $school]))
            ->greeting(__('Dzień dobry :name!', ['name' => $notifiable->first_name]))
            ->line(__('Administrator utworzył dla Ciebie konto w systemie :app.', ['app' => $school]))
            ->line(__('Ustaw hasło, aby się zalogować. Twój login to: :email', ['email' => $notifiable->email]))
            ->action(__('Ustaw hasło'), $url)
            ->line(__('Link jest ważny przez :count minut. Po tym czasie poproś administratora o nowy.', ['count' => $expire]));
    }
}
