<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Server-side allow-list for HTML coming from the shared editor
 * (docs/ARCHITECTURE.md §12.1). Never trust the browser: everything stored
 * passes through sanitize(); mentions are then validated by Mentions.
 */
class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): string
    {
        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('em')
                ->allowElement('s')
                ->allowElement('u')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('blockquote')
                ->allowElement('code')
                ->allowElement('pre')
                ->allowElement('hr')
                ->allowElement('a', ['href'])
                ->allowElement('span', ['data-type', 'data-id', 'data-label'])
                ->allowLinkSchemes(['https', 'http', 'mailto'])
                ->allowRelativeLinks(false)
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->forceAttribute('a', 'target', '_blank')
                ->withMaxInputLength(200_000)
        );

        return trim(self::$sanitizer->sanitize((string) $html));
    }

    public static function plain(?string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/li|\/h[23]|\/blockquote)[^>]*>/i', "$0\n", (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $text)));
    }

    public static function excerpt(?string $html, int $limit = 180): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', self::plain($html)), $limit);
    }

    public static function isBlank(?string $html): bool
    {
        return trim(self::plain($html)) === '';
    }
}
