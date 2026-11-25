<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * One stored image (logo, cover, post image) on the private `media` disk.
 * Files are served by MediaController under an unguessable uuid URL.
 *
 * @property string $uuid
 * @property string $path
 */
class Media extends Model
{
    protected $fillable = [
        'uuid', 'school_id', 'owner_type', 'owner_id', 'collection', 'path', 'mime',
        'size', 'width', 'height', 'is_public', 'position', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function extension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /** Relative URL, so a wrong APP_URL or a sub-folder install never breaks images. */
    public function url(): string
    {
        return route('media.show', ['uuid' => $this->uuid, 'ext' => $this->extension()], absolute: false);
    }

    /** Removes the database row and the file. */
    public function purge(): void
    {
        $path = $this->path;
        $this->delete();
        Storage::disk('media')->delete($path);
    }
}
