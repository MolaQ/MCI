<?php

namespace App\Domain\Media\Actions;

use App\Models\Media;
use App\Models\School;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Points an image column (schools.logo_media_id, groups.logo_media_id,
 * events.cover_media_id) at a newly stored image, or clears it.
 *
 * Rollback-safe ordering: the new file is stored first, the record is
 * updated, and only then is the old image removed. If saving the record
 * fails the new file is removed again and the old one stays untouched.
 */
class ReplaceImage
{
    public function __construct(private StoreImage $store) {}

    public function handle(Model $model, string $column, ?UploadedFile $file, string $collection, bool $public, ?User $actor, string $field, bool $remove = false): void
    {
        if (! $file && ! $remove) {
            return;
        }

        $old = $model->getAttribute($column) ? Media::query()->find($model->getAttribute($column)) : null;
        $schoolId = $model instanceof School ? $model->getKey() : $model->getAttribute('school_id');
        $new = $file ? $this->store->handle($file, $collection, $schoolId, $actor, $model, $public, $field) : null;

        try {
            $model->forceFill([$column => $new?->id])->save();
        } catch (\Throwable $e) {
            $new?->purge();

            throw $e;
        }

        $old?->purge();

        Audit::log($new ? 'media.image_replaced' : 'media.image_removed', $model, [
            'field' => $column, 'size' => $new?->size, 'dimensions' => $new ? $new->width.'×'.$new->height : null,
        ], $actor, $schoolId);
    }

    /** Keeps a record's image public flag in line with the record's own visibility. */
    public static function syncVisibility(?int $mediaId, bool $public): void
    {
        if ($mediaId) {
            Media::query()->whereKey($mediaId)->update(['is_public' => $public]);
        }
    }
}
