<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Support;

/**
 * @internal
 */
final class TextHelper
{
    /**
     * Plain text of an HTML fragment; block-level boundaries become spaces so words don't merge.
     */
    public static function htmlToText(string $html): string
    {
        $spaced = preg_replace('~(</p>|</div>|</li>|<br\s*/?>)~i', '$1 ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }

    public static function isBlank(string $html): bool
    {
        return self::htmlToText($html) === '';
    }
}
