<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Support\QrCode;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvitePosterController extends Controller
{
    public function poster(Invite $invite): View
    {
        Gate::authorize('view', $invite);

        return view('invites.poster', [
            'invite' => $invite->load('school', 'groups'),
            'qr' => QrCode::svg($invite->url(), 640),
        ]);
    }

    public function svg(Invite $invite): Response
    {
        Gate::authorize('view', $invite);

        return response(QrCode::svg($invite->url(), 1024), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="mci-zaproszenie-'.$invite->id.'.svg"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
