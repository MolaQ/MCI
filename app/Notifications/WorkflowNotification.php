<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkflowRequest;
use App\Support\Push;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A workflow event for one person: in the bell always; as Web Push when
 * push is on and the person keeps "Obiegi i wnioski" (or "Wzmianki")
 * switched on in Ustawienia → Powiadomienia.
 */
class WorkflowNotification extends Notification
{
    public function __construct(public WorkflowRequest $request, public string $kind, public ?User $actor = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable instanceof User && Push::enabled($this->request->school_id) && $notifiable->pushSubscriptions->isNotEmpty()
            && $notifiable->wantsPush($this->kind === 'mention' ? 'mentions' : 'workflows')) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'workflow_'.$this->kind,
            'title' => $this->title(),
            'body' => $this->request->title,
            'school' => $this->request->school?->short_name,
            'priority' => in_array($this->kind, ['pending', 'overdue', 'returned'], true) ? 'important' : 'normal',
            'url' => route('workflows.show', $this->request),
            'workflow_request_id' => $this->request->id,
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        $r = $this->request;

        return (new WebPushMessage)
            ->title(($r->school?->short_name ? $r->school->short_name.' · ' : '').$this->title())
            ->body($r->title."\n".$r->template_name)
            ->icon('/pwa/icon-192.png')
            ->badge('/pwa/icon-96-badge.png')
            ->tag('workflow-'.$r->id.'-'.$this->kind)
            ->lang('pl')
            ->requireInteraction($this->kind === 'pending' || $this->kind === 'overdue')
            ->data(['url' => route('workflows.show', $r, absolute: false)])
            ->options(['TTL' => 86400, 'urgency' => $this->kind === 'overdue' ? 'high' : 'normal', 'topic' => 'workflow-'.$r->id]);
    }

    private function title(): string
    {
        $who = $this->actor?->name ?? __('Ktoś');
        $stage = $this->request->currentStage()['name'] ?? '';

        return match ($this->kind) {
            'pending' => __('Wniosek czeka na Twoją decyzję: :stage', ['stage' => $stage]),
            'overdue' => __('Minął termin decyzji: :stage', ['stage' => $stage]),
            'returned' => __(':author zwrócił(a) wniosek do poprawy', ['author' => $who]),
            'approved' => __('Wniosek zatwierdzony'),
            'rejected' => __(':author odrzucił(a) wniosek', ['author' => $who]),
            'mention' => __(':author wspomniał(a) o Tobie we wniosku', ['author' => $who]),
            default => __('Wniosek'),
        };
    }
}
