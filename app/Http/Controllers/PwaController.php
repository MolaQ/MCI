<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Support\Appearance;
use GdImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Installable app (Step 4): web manifest, generated icons, the service
 * worker and the offline page. Everything is plain PHP + files, so it works
 * on shared hosting; icons are rendered once with GD and cached.
 */
class PwaController
{
    public const ICON_SIZES = [192, 512];

    /** PNG favicons linked from <head>; 180 is the Apple touch icon. */
    public const FAVICON_SIZES = [16, 32, 48, 180];

    /** Sizes packed into /favicon.ico. */
    public const ICO_SIZES = [16, 32, 48];

    public function __construct(private Appearance $appearance) {}

    public function manifest(): JsonResponse
    {
        $color = Appearance::sanitizeHex($this->appearance->get('primary_color'), '#1d4ed8');
        $icons = [];
        foreach (self::ICON_SIZES as $size) {
            $icons[] = ['src' => route('pwa.icon', ['size' => $size], false), 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => route('pwa.icon.variant', ['size' => $size, 'variant' => 'maskable'], false), 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'maskable'];
        }

        return response()->json([
            'id' => '/pulpit',
            'name' => $this->appearance->shortName().' · '.$this->appearance->schoolName(),
            'short_name' => $this->appearance->shortName(),
            'description' => __('Komunikaty, wydarzenia i alarmy szkoły.'),
            'lang' => 'pl',
            'dir' => 'ltr',
            'start_url' => '/pulpit?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#ffffff',
            'theme_color' => $color,
            'icons' => $icons,
            'shortcuts' => [
                ['name' => __('Komunikaty'), 'url' => '/komunikaty', 'icons' => [['src' => route('pwa.icon', ['size' => 192], false), 'sizes' => '192x192']]],
                ['name' => __('Wydarzenia'), 'url' => '/wydarzenia', 'icons' => [['src' => route('pwa.icon', ['size' => 192], false), 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function icon(int $size, string $variant = 'any'): Response
    {
        abort_unless(in_array($size, [...self::ICON_SIZES, 96], true) && in_array($variant, ['any', 'maskable', 'badge'], true), 404);

        return $this->pngResponse($this->cached($size, $variant));
    }

    /** PNG favicon (16/32/48) and Apple touch icon (180). */
    public function favicon(int $size): Response
    {
        abort_unless(in_array($size, self::FAVICON_SIZES, true), 404);

        return $this->pngResponse($this->cached($size, $size >= 180 ? 'touch' : 'favicon'));
    }

    /** /favicon.ico: PNG-compressed entries (16, 32, 48), valid in every current browser. */
    public function faviconIco(): Response
    {
        $images = array_map(fn (int $size) => File::get($this->cached($size, 'favicon')), self::ICO_SIZES);

        $header = pack('vvv', 0, 1, count($images));
        $directory = '';
        $offset = 6 + 16 * count($images);
        foreach (self::ICO_SIZES as $i => $size) {
            $directory .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($images[$i]), $offset);
            $offset += strlen($images[$i]);
        }

        return response($header.$directory.implode('', $images), 200, [
            'Content-Type' => 'image/x-icon',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Version string for <head> links, so browsers fetch a new favicon after a change. */
    public static function version(Appearance $appearance): string
    {
        return substr(md5(implode('|', [$appearance->get('favicon_media_id'), $appearance->get('logo_media_id'), $appearance->get('primary_color'), $appearance->shortName()])), 0, 8);
    }

    /** Render once per look (favicon, logo, colour, name) and keep the file. */
    private function cached(int $size, string $variant): string
    {
        $color = Appearance::sanitizeHex($this->appearance->get('primary_color'), '#1d4ed8');
        $logoId = (int) $this->appearance->get('logo_media_id');
        $faviconId = (int) $this->appearance->get('favicon_media_id');
        $key = md5(implode('|', [$size, $variant, $color, $logoId, $faviconId, $this->appearance->shortName(), 3]));
        $path = storage_path("app/pwa/{$key}.png");

        if (! File::exists($path)) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $this->render($size, $variant, $color,
                $logoId ? Media::query()->find($logoId) : null,
                $faviconId ? Media::query()->find($faviconId) : null));
        }

        return $path;
    }

    private function pngResponse(string $path): Response
    {
        return response(File::get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function serviceWorker(): Response
    {
        $manifest = public_path('build/manifest.json');
        $version = substr(md5((File::exists($manifest) ? File::lastModified($manifest) : 0).config('app.key')), 0, 10);

        $js = str_replace(
            ['__VERSION__', '__OFFLINE_URL__'],
            [$version, route('pwa.offline', [], false)],
            File::get(resource_path('js/sw.js')),
        );

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline(): View
    {
        return view('pwa.offline');
    }

    private function render(int $size, string $variant, string $color, ?Media $logo, ?Media $favicon = null): string
    {
        $img = imagecreatetruecolor($size, $size);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        if ($variant === 'badge') {
            // Monochrome silhouette for Android's status bar.
            imagealphablending($img, false);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
            imagealphablending($img, true);
            $this->letters($img, $size, imagecolorallocate($img, 255, 255, 255), 0.5);

            return $this->png($img);
        }

        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $faviconImage = $this->image($favicon);

        if ($faviconImage instanceof GdImage) {
            // The favicon from Wygląd: its own shape and transparency are kept.
            // Maskable/touch icons need a full background, so they sit on white.
            $full = in_array($variant, ['any', 'favicon'], true);
            imagealphablending($img, false);
            imagefill($img, 0, 0, $full ? imagecolorallocatealpha($img, 0, 0, 0, 127) : imagecolorallocate($img, 255, 255, 255));
            imagealphablending($img, true);
            $box = (int) round($size * match ($variant) {
                'maskable' => 0.7, 'touch' => 0.86, default => 1.0
            });
            $this->paste($img, $faviconImage, $size, $box);

            return $this->png($img);
        }

        $logoImage = $this->image($logo);

        if ($variant === 'favicon') {
            // Default favicon: rounded square in the theme colour with the first letter.
            imagealphablending($img, false);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
            imagealphablending($img, true);
            $this->roundedSquare($img, $size, imagecolorallocate($img, $r, $g, $b));
            $this->letters($img, $size, imagecolorallocate($img, 255, 255, 255), 0.62, 1);

            return $this->png($img);
        }

        if ($logoImage instanceof GdImage) {
            // Logo centred on white; maskable icons keep the 80 % safe zone.
            imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
            $box = (int) round($size * ($variant === 'maskable' ? 0.62 : 0.84));
            $this->paste($img, $logoImage, $size, $box);
        } else {
            imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
            $this->letters($img, $size, imagecolorallocate($img, 255, 255, 255), $variant === 'maskable' ? 0.34 : 0.42);
        }

        return $this->png($img);
    }

    private function image(?Media $media): ?GdImage
    {
        if (! $media || ! Storage::disk('media')->exists($media->path)) {
            return null;
        }
        $image = @imagecreatefromstring(Storage::disk('media')->get($media->path));

        return $image instanceof GdImage ? $image : null;
    }

    /** Fit $source into a centred $box × $box square of $img. */
    private function paste(GdImage $img, GdImage $source, int $size, int $box): void
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min($box / $w, $box / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        imagecopyresampled($img, $source, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    }

    private function roundedSquare(GdImage $img, int $size, int $colour): void
    {
        $r = max(2, (int) round($size * 0.22));
        imagefilledrectangle($img, $r, 0, $size - 1 - $r, $size - 1, $colour);
        imagefilledrectangle($img, 0, $r, $size - 1, $size - 1 - $r, $colour);
        foreach ([[$r, $r], [$size - 1 - $r, $r], [$r, $size - 1 - $r], [$size - 1 - $r, $size - 1 - $r]] as [$x, $y]) {
            imagefilledellipse($img, $x, $y, $r * 2, $r * 2, $colour);
        }
    }

    /** Short name (max 4 letters), with the bundled DejaVu font when FreeType is there. */
    private function letters(GdImage $img, int $size, int $colour, float $height, int $max = 4): void
    {
        $text = mb_strtoupper(mb_substr($this->appearance->shortName() ?: 'SOWA', 0, $max));
        $ttf = resource_path('fonts/DejaVuSans-Bold.ttf');

        if (function_exists('imagettftext') && is_file($ttf)) {
            $points = $size * $height * 0.75;
            $box = imagettfbbox($points, 0, $ttf, $text);
            $w = $box[2] - $box[0];
            if ($w > $size * 0.8) {
                $points *= ($size * 0.8) / $w;
                $box = imagettfbbox($points, 0, $ttf, $text);
                $w = $box[2] - $box[0];
            }
            $h = $box[1] - $box[7];
            imagettftext($img, $points, 0, (int) (($size - $w) / 2 - $box[0]), (int) (($size + $h) / 2 - $box[1]), $colour, $ttf, $text);

            return;
        }

        $font = 5;
        $tw = imagefontwidth($font) * strlen($text);
        $th = imagefontheight($font);
        $small = imagecreatetruecolor($tw, $th);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagestring($small, $font, 0, 0, $text, $colour);
        $targetW = (int) min($size * 0.8, round($tw * $size * $height / $th));
        $targetH = (int) round($th * $targetW / $tw);
        imagecopyresampled($img, $small, (int) (($size - $targetW) / 2), (int) (($size - $targetH) / 2), 0, 0, $targetW, $targetH, $tw, $th);
    }

    private function png(GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 9);

        return (string) ob_get_clean();
    }
}
