<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Support;

use Dandrum\PageBuilder\Contracts\HtmlSanitizer;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

final class PurifierHtmlSanitizer implements HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        return trim($this->purifier()->purify($html));
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier !== null) {
            return $this->purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', (string) config('pagebuilder.sanitizer.allowed', 'p,div,br,strong,em,a[href],ul,ol,li'));
        $config->set('URI.AllowedSchemes', array_fill_keys((array) config('pagebuilder.sanitizer.schemes', ['http', 'https', 'mailto']), true));
        $config->set('HTML.Nofollow', (bool) config('pagebuilder.sanitizer.nofollow', true));
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);

        $cachePath = config('pagebuilder.sanitizer.cache_path');

        if ($cachePath === false) {
            $config->set('Cache.DefinitionImpl', null);
        } else {
            $path = is_string($cachePath) && $cachePath !== '' ? $cachePath : storage_path('framework/cache/pagebuilder-purifier');
            File::ensureDirectoryExists($path);
            $config->set('Cache.SerializerPath', $path);
        }

        return $this->purifier = new HTMLPurifier($config);
    }
}
