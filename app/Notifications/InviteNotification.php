<?php

namespace App\Notifications;

use App\Models\Invite;
use App\Support\Appearance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public Invite $invite, public string $token)
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
        $school = $this->invite->school;
        $groups = $this->invite->groups->pluck('name')->join(', ');

        $mail = (new MailMessage)
            ->subject(__('Zaproszenie do :school w :app', ['school' => $school->short_name, 'app' => app(Appearance::class)->shortName()]))
            ->greeting(__('Dzień dobry!'))
            ->line(__(':inviter zaprasza Cię do szkoły :school w systemie :app.', [
                'app' => app(Appearance::class)->shortName(),
                'inviter' => $this->invite->creator?->name ?? __('Administrator'),
                'school' => $school->name,
            ]));

        if ($groups !== '') {
            $mail->line(__('Zostaniesz dodany(-a) do grup: :groups.', ['groups' => $groups]));
        }

        return $mail
            ->action(__('Przyjmij zaproszenie'), route('join.show', $this->token))
            ->line(__('Zaproszenie jest ważne do :date.', ['date' => $this->invite->expires_at?->timezone(config('app.timezone'))->format('d.m.Y H:i')]))
            ->line(__('Jeśli nie spodziewasz się tej wiadomości, zignoruj ją.'));
    }
}
