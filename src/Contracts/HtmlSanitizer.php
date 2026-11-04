<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Contracts;

interface HtmlSanitizer
{
    /**
     * Returns `$html` reduced to the configured whitelist of tags/attributes/URI schemes.
     * Must be idempotent: sanitizing already-sanitized output returns it unchanged.
     */
    public function sanitize(string $html): string;
}
