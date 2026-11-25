<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves stored images. Public media (logos, covers of public content) go
 * to anyone with long-lived caching; everything else needs a login and
 * access to the owning record (or membership of its school). Anything
 * else is a 404, so private URLs do not even confirm they exist.
 */
class MediaController
{
    public function show(Request $request, string $uuid, string $ext): BinaryFileResponse
    {
        $media = Media::query()->where('uuid', $uuid)->first();
        abort_if(! $media || $media->extension() !== $ext, 404);

        if (! $media->is_public) {
            $user = $request->user();
            $owner = $media->owner;
            $allowed = $user && ($owner
                ? Gate::forUser($user)->allows('view', $owner)
                : ($media->school_id === null || $user->belongsToSchool($media->school_id)));
            abort_unless($allowed, 404);
        }

        $disk = Storage::disk('media');
        abort_unless($disk->exists($media->path), 404);

        return response()->file($disk->path($media->path), [
            'Content-Type' => $media->mime,
            'Cache-Control' => $media->is_public ? 'public, max-age=31536000, immutable' : 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
