<?php

namespace App\Livewire\Publicly;

use App\Enums\Feature;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\School;
use App\Support\Features;
use App\Support\Mentions;
use App\Support\RichText;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::public')]
class SchoolPage extends Component
{
    #[Locked]
    public int $schoolId;

    public function mount(string $slug): void
    {
        $school = School::query()->publiclyListed()->where('slug', $slug)->first();
        abort_unless($school, 404);
        $this->schoolId = $school->id;
    }

    public function render()
    {
        $school = School::query()->publiclyListed()->findOrFail($this->schoolId);
        $offers = app(Features::class)->enabled(Feature::PublicOffers, $school)
            ? $school->offers()->where('is_active', true)->orderBy('position')->get()
            : collect();

        return view('livewire.public.school', [
            'school' => $school,
            'offers' => $offers,
            'events' => Event::query()->publiclyVisible()->where('events.school_id', $school->id)->upcoming()->orderBy('events.starts_at')->limit(12)->get(),
            'announcements' => Announcement::query()->publiclyVisible()->where('announcements.school_id', $school->id)->latest('published_at')->limit(6)->get(['id', 'school_id', 'title', 'body', 'published_at']),
            'excerpt' => fn (string $html) => RichText::excerpt(Mentions::forPublic($html), 200),
        ])->title($school->name);
    }
}
