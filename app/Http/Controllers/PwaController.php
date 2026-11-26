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

        $color = Appearance::sanitizeHex($this->appearance->get('primary_color'), '#1d4ed8');
        $logoId = (int) $this->appearance->get('logo_media_id');
        $key = md5(implode('|', [$size, $variant, $color, $logoId, $this->appearance->shortName(), 2]));
        $path = storage_path("app/pwa/{$key}.png");

        if (! File::exists($path)) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $this->render($size, $variant, $color, $logoId ? Media::query()->find($logoId) : null));
        }

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

    private function render(int $size, string $variant, string $color, ?Media $logo): string
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
        $logoImage = $logo && Storage::disk('media')->exists($logo->path)
            ? @imagecreatefromstring(Storage::disk('media')->get($logo->path)) : null;

        if ($logoImage instanceof GdImage) {
            // Logo centred on white; maskable icons keep the 80 % safe zone.
            imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
            $box = (int) round($size * ($variant === 'maskable' ? 0.62 : 0.84));
            $w = imagesx($logoImage);
            $h = imagesy($logoImage);
            $scale = min($box / $w, $box / $h);
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            imagecopyresampled($img, $logoImage, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
        } else {
            imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
            $this->letters($img, $size, imagecolorallocate($img, 255, 255, 255), $variant === 'maskable' ? 0.34 : 0.42);
        }

        return $this->png($img);
    }

    /** Short name (max 4 letters), with the bundled DejaVu font when FreeType is there. */
    private function letters(GdImage $img, int $size, int $colour, float $height): void
    {
        $text = mb_strtoupper(mb_substr($this->appearance->shortName() ?: 'SOWA', 0, 4));
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
