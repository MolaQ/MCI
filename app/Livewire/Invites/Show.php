<?php

namespace App\Livewire\Invites;

use App\Models\Invite;
use App\Support\Audit;
use App\Support\QrCode;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Invite $invite;

    public function mount(Invite $invite): void
    {
        $this->authorize('view', $invite);
    }

    #[Computed]
    public function qr(): string
    {
        return QrCode::svg($this->invite->url(), 280);
    }

    public function revoke(): void
    {
        $this->authorize('revoke', $this->invite);

        $this->invite->forceFill(['revoked_at' => now(), 'revoked_by' => Auth::id()])->save();
        Audit::log('invite.revoked', $this->invite, schoolId: $this->invite->school_id);

        Flux::toast(text: __('Zaproszenie unieważnione. Link i kod QR przestały działać.'));
    }

    public function render()
    {
        return view('livewire.invites.show', [
            'redemptions' => $this->invite->redemptions()->with('user')->latest('redeemed_at')->limit(100)->get(),
        ])->title($this->invite->label ?: __('Zaproszenie'));
    }
}
