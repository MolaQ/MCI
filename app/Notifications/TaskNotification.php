<?php

namespace App\Notifications;

use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\User;
use App\Support\Push;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A task event for one person: always in the bell; on the phone (Web Push)
 * when push is on for the school and the person keeps "Zadania" (or
 * "Wzmianki" for mentions) switched on in Ustawienia → Powiadomienia.
 *
 * Kinds: assigned, mention, completed, cancelled, due_soon, overdue.
 */
class TaskNotification extends Notification
{
    public function __construct(public Task $task, public string $kind, public ?User $actor = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && Push::enabled($this->task->school_id) && $notifiable->pushSubscriptions->isNotEmpty()
            && $notifiable->wantsPush($this->kind === 'mention' ? 'mentions' : 'tasks')) {
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
            'kind' => 'task_'.$this->kind,
            'title' => $this->title(),
            'body' => $this->task->title,
            'school' => $this->task->school?->short_name,
            'priority' => $this->task->priority === TaskPriority::Urgent ? 'urgent' : ($this->task->priority === TaskPriority::High ? 'important' : 'normal'),
            'url' => route('tasks.show', $this->task),
            'task_id' => $this->task->id,
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        $t = $this->task;
        $urgent = $t->priority === TaskPriority::Urgent || $this->kind === 'overdue';
        $due = $t->dueLabel();

        return (new WebPushMessage)
            ->title(($t->school?->short_name ? $t->school->short_name.' · ' : '').$this->title())
            ->body($t->title.($due ? "\n".__('Termin: :due', ['due' => $due]) : ''))
            ->icon('/pwa/icon-192.png')
            ->badge('/pwa/icon-96-badge.png')
            ->tag('task-'.$t->id.'-'.$this->kind)
            ->lang('pl')
            ->requireInteraction($urgent)
            ->data(['url' => route('tasks.show', $t, absolute: false)])
            ->options(['TTL' => 86400, 'urgency' => $urgent ? 'high' : 'normal', 'topic' => 'task-'.$t->id]);
    }

    private function title(): string
    {
        $who = $this->actor?->name ?? __('Ktoś');

        return match ($this->kind) {
            'assigned' => __(':author przydzielił(a) Ci zadanie', ['author' => $who]),
            'mention' => __(':author wspomniał(a) o Tobie w zadaniu', ['author' => $who]),
            'completed' => __(':author wykonał(a) zadanie', ['author' => $who]),
            'cancelled' => __(':author anulował(a) zadanie', ['author' => $who]),
            'due_soon' => __('Zbliża się termin zadania'),
            'overdue' => __('Zadanie po terminie'),
            default => __('Zadanie'),
        };
    }
}
