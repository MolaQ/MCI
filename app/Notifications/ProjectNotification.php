<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Support\Push;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A project event for one person: always in the bell; on the phone when
 * push is on for the school and the person keeps "Projekty" (or "Wzmianki")
 * switched on.
 *
 * Kinds: assigned, coordinator, mention, status, milestone_due, milestone_overdue.
 */
class ProjectNotification extends Notification
{
    public function __construct(public Project $project, public string $kind, public ?User $actor = null, public ?ProjectMilestone $milestone = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && Push::enabled($this->project->school_id) && $notifiable->pushSubscriptions->isNotEmpty()
            && $notifiable->wantsPush($this->kind === 'mention' ? 'mentions' : 'projects')) {
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
            'kind' => 'project_'.$this->kind,
            'title' => $this->title(),
            'body' => $this->body(),
            'school' => $this->project->school?->short_name,
            'priority' => $this->kind === 'milestone_overdue' ? 'important' : 'normal',
            'url' => route('projects.show', $this->project),
            'project_id' => $this->project->id,
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        $p = $this->project;
        $urgent = $this->kind === 'milestone_overdue';

        return (new WebPushMessage)
            ->title(($p->school?->short_name ? $p->school->short_name.' · ' : '').$this->title())
            ->body($this->body())
            ->icon('/pwa/icon-192.png')
            ->badge('/pwa/icon-96-badge.png')
            ->tag('project-'.$p->id.'-'.$this->kind.($this->milestone ? '-'.$this->milestone->id : ''))
            ->lang('pl')
            ->requireInteraction($urgent)
            ->data(['url' => route('projects.show', $p, absolute: false)])
            ->options(['TTL' => 86400, 'urgency' => $urgent ? 'high' : 'normal', 'topic' => 'project-'.$p->id]);
    }

    private function body(): string
    {
        return match (true) {
            $this->milestone !== null => $this->project->name.' · '.$this->milestone->title
                .($this->milestone->due_at ? "\n".__('Termin: :due', ['due' => $this->milestone->due_at->translatedFormat('j M, H:i')]) : ''),
            $this->kind === 'status' => $this->project->name.' · '.$this->project->status->label(),
            default => $this->project->name,
        };
    }

    private function title(): string
    {
        $who = $this->actor?->name ?? __('Ktoś');

        return match ($this->kind) {
            'assigned' => __(':author dodał(a) Cię do zespołu projektu', ['author' => $who]),
            'coordinator' => __(':author powierzył(a) Ci koordynację projektu', ['author' => $who]),
            'mention' => __(':author wspomniał(a) o Tobie w projekcie', ['author' => $who]),
            'status' => __(':author zmienił(a) status projektu', ['author' => $who]),
            'milestone_due' => __('Zbliża się termin kamienia milowego'),
            'milestone_overdue' => __('Kamień milowy po terminie'),
            default => __('Projekt'),
        };
    }
}
