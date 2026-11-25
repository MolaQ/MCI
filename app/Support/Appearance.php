<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resolves the configurable look of the app (Administracja → Wygląd).
 *
 * Values come from config('mci.appearance') overridden by the `appearance`
 * setting, and are emitted as CSS custom properties in the layout <head>.
 * Flux UI reads --color-accent*, and our own components read --mci-*, so
 * changing a colour here re-themes the whole interface without a rebuild.
 */
class Appearance
{
    public const RADII = [
        'none' => '0px',
        'sm' => '0.25rem',
        'md' => '0.5rem',
        'lg' => '0.75rem',
        'xl' => '1rem',
    ];

    public const THEMES = ['system', 'light', 'dark'];

    public function __construct(private Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge(
            config('mci.appearance'),
            array_filter((array) $this->settings->get('appearance', []), fn ($v) => $v !== null && $v !== ''),
        );
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function schoolName(): string
    {
        return (string) $this->get('school_name');
    }

    public function shortName(): string
    {
        return (string) $this->get('short_name');
    }

    public function logoUrl(): ?string
    {
        $path = $this->get('logo_path');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function defaultTheme(): string
    {
        $theme = (string) $this->get('default_theme');

        return in_array($theme, self::THEMES, true) ? $theme : 'system';
    }

    public function cssVariables(): string
    {
        $a = $this->all();
        $primary = self::sanitizeHex($a['primary_color'], '#1d4ed8');
        $foreground = self::sanitizeHex($a['primary_foreground'], '#ffffff');
        $danger = self::sanitizeHex($a['danger_color'], '#dc2626');
        $radius = self::RADII[$a['radius']] ?? self::RADII['md'];

        return ":root{--mci-primary:{$primary};--mci-primary-foreground:{$foreground};--mci-danger:{$danger};"
            ."--mci-radius:{$radius};--color-accent:{$primary};--color-accent-content:{$primary};"
            ."--color-accent-foreground:{$foreground};}"
            .".dark{--color-accent:{$primary};--color-accent-content:color-mix(in oklab,{$primary} 70%,white);"
            ."--color-accent-foreground:{$foreground};}";
    }

    public static function sanitizeHex(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }
}
