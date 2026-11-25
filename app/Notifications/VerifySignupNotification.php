<?php

namespace App\Notifications;

use App\Models\School;
use App\Support\Appearance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifySignupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public School $school)
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

    public static function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute('join.verify', now()->addHours(config('mci.invites.verification_hours')), [
            'user' => $notifiable->getKey(),
            'hash' => sha1($notifiable->email),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Potwierdź adres e-mail — :app', ['app' => app(Appearance::class)->shortName()]))
            ->greeting(__('Dzień dobry :name!', ['name' => $notifiable->first_name]))
            ->line(__('Utworzono konto w :app ze szkołą :school. Potwierdź, że ten adres należy do Ciebie.', ['school' => $this->school->name, 'app' => app(Appearance::class)->shortName()]))
            ->action(__('Potwierdzam adres e-mail'), static::verificationUrl($notifiable))
            ->line(__('Link jest ważny :hours godz. Bez potwierdzenia nie można się zalogować.', ['hours' => config('mci.invites.verification_hours')]));
    }
}
