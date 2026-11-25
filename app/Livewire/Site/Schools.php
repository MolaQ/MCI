<?php

namespace App\Livewire\Site;

use App\Models\School;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Szkoły')]
class Schools extends Component
{
    public function mount(): void
    {
        $this->authorize('site-admin');
    }

    #[Computed]
    public function schools()
    {
        return School::query()->withCount(['users', 'groups'])->orderBy('name')->get();
    }

    public function render()
    {
        $this->authorize('site-admin');

        return view('livewire.site.schools');
    }
}
