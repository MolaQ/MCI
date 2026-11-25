<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Called by the service worker on `pushsubscriptionchange`. There is no
 * session there, so the old endpoint (a long unguessable URL only the
 * browser knows) proves ownership; the new subscription replaces it for
 * the same user and device.
 */
class PushSubscriptionController
{
    public function renew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'old_endpoint' => ['required', 'string', 'url', 'max:1024'],
            'subscription.endpoint' => ['required', 'string', 'url', 'max:1024', 'starts_with:https://'],
            'subscription.keys.p256dh' => ['required', 'string', 'max:255'],
            'subscription.keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $old = PushSubscription::query()->where('endpoint', $data['old_endpoint'])->first();
        abort_unless($old, 404);

        PushSubscription::query()->where('endpoint', $data['subscription']['endpoint'])->whereKeyNot($old->id)->delete();
        $old->update([
            'endpoint' => $data['subscription']['endpoint'],
            'public_key' => $data['subscription']['keys']['p256dh'],
            'auth_token' => $data['subscription']['keys']['auth'],
            'failed_at' => null,
        ]);

        return response()->json(['ok' => true]);
    }
}
