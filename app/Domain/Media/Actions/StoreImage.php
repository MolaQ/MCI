<?php

namespace App\Domain\Media\Actions;

use App\Models\Media;
use App\Models\User;
use GdImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns an uploaded file into a safe, optimised image on the media disk.
 *
 *  - the type is read from the bytes (not the name or the browser's MIME);
 *    only PNG, JPEG, WebP and GIF are accepted, never SVG;
 *  - the size is checked before decoding, to refuse decompression bombs;
 *  - the image is decoded and re-encoded with GD, which drops EXIF/GPS data
 *    and anything hidden in the file; JPEG orientation is applied first;
 *  - it is downscaled to the collection's limit and saved as WebP with
 *    alpha (PNG when GD has no WebP), so transparent logos stay transparent.
 *
 * The browser editor does crop/zoom/background removal; the server never
 * trusts its output and runs the same pipeline on it.
 */
class StoreImage
{
    private const TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP, IMAGETYPE_GIF];

    public function handle(UploadedFile $file, string $collection, ?int $schoolId, ?User $actor, ?Model $owner = null, bool $public = false, string $field = 'image'): Media
    {
        $maxSide = config("mci.media.collections.{$collection}");
        abort_if($maxSide === null, 500, "Unknown media collection [{$collection}].");

        $image = $this->decode($file, $field);
        $image = $this->fit($image, (int) $maxSide);

        $webp = function_exists('imagewebp');
        $ext = $webp ? 'webp' : 'png';
        $path = $collection.'/'.now()->format('Y/m').'/'.Str::uuid()->toString().'.'.$ext;

        ob_start();
        $webp ? imagewebp($image, null, (int) config('mci.media.quality')) : imagepng($image, null, 6);
        $bytes = (string) ob_get_clean();

        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        Storage::disk('media')->put($path, $bytes);

        try {
            return Media::query()->create([
                'uuid' => (string) Str::uuid(),
                'school_id' => $schoolId,
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
                'collection' => $collection,
                'path' => $path,
                'mime' => $webp ? 'image/webp' : 'image/png',
                'size' => strlen($bytes),
                'width' => $width,
                'height' => $height,
                'is_public' => $public,
                'uploaded_by' => $actor?->id,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('media')->delete($path);

            throw $e;
        }
    }

    private function decode(UploadedFile $file, string $field): GdImage
    {
        $fail = fn (string $message) => throw ValidationException::withMessages([$field => $message]);

        $realPath = $file->getRealPath();
        $info = $realPath ? @getimagesize($realPath) : false;
        if (! $info || ! in_array($info[2], self::TYPES, true)) {
            $fail(__('Plik nie jest obrazem PNG, JPG, WebP ani GIF.'));
        }
        if ($info[0] < 1 || $info[1] < 1 || config('mci.media.max_pixels') < $info[0] * $info[1]) {
            $fail(__('Obraz ma zbyt dużą rozdzielczość.'));
        }

        $image = @imagecreatefromstring((string) file_get_contents($realPath));
        if (! $image instanceof GdImage) {
            $fail(__('Nie udało się odczytać obrazu. Spróbuj zapisać go ponownie jako PNG lub JPG.'));
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($realPath)['Orientation'] ?? 1);
            $image = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
        }

        return $image;
    }

    private function fit(GdImage $image, int $maxSide): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $maxSide / max($w, $h));
        if ($scale >= 1) {
            return $image;
        }

        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($image);

        return $out;
    }
}
