<?php

namespace App\Livewire\Events;

use App\Models\Event;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $eventId;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->eventId = $event->id;
    }

    public function render()
    {
        $event = Event::query()->with(['school', 'owner'])->findOrFail($this->eventId);
        $this->authorize('view', $event);

        return view('livewire.events.show', ['event' => $event])->title($event->title);
    }
}
