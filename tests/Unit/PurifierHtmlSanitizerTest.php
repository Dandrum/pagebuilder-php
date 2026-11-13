<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Unit;

use Dandrum\PageBuilder\Contracts\HtmlSanitizer;
use Dandrum\PageBuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PurifierHtmlSanitizerTest extends TestCase
{
    private function sanitizer(): HtmlSanitizer
    {
        return $this->app->make(HtmlSanitizer::class);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedMarkup(): array
    {
        return [
            'strong' => ['<p>a <strong>b</strong></p>'],
            'em' => ['<p>a <em>b</em></p>'],
            'bullet list' => ['<ul><li>a</li><li>b</li></ul>'],
            'numbered list' => ['<ol><li>a</li></ol>'],
            'div' => ['<div>a</div>'],
            'br' => ['<div>a<br>b</div>'],
            'umlauts' => ['<p>Größe ÄÖÜ äöü ß</p>'],
            'h1' => ['<h1>a</h1>'],
            'h2' => ['<h2>a</h2>'],
            'h3' => ['<h3>a</h3>'],
            'h4' => ['<h4>a</h4>'],
            'h5' => ['<h5>a</h5>'],
            'h6' => ['<h6>a</h6>'],
            'blockquote' => ['<blockquote>a</blockquote>'],
            'heading with formatting' => ['<h2>a <strong>b</strong> <a href="https://example.com" rel="nofollow">c</a></h2>'],
        ];
    }

    #[DataProvider('allowedMarkup')]
    public function test_allowed_markup_survives(string $html): void
    {
        $this->assertSame($html, $this->sanitizer()->sanitize($html));
    }

    public function test_links_keep_href_and_get_nofollow(): void
    {
        $out = $this->sanitizer()->sanitize('<p><a href="https://example.com/x?a=1">x</a></p>');

        $this->assertStringContainsString('href="https://example.com/x?a=1"', $out);
        $this->assertStringContainsString('rel="nofollow"', $out);
    }

    public function test_mailto_links_are_kept(): void
    {
        $this->assertStringContainsString('href="mailto:a@example.com"', $this->sanitizer()->sanitize('<p><a href="mailto:a@example.com">m</a></p>'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function dangerousMarkup(): array
    {
        return [
            'script' => ['<p>ok</p><script>alert(1)</script>', 'script'],
            'onclick' => ['<p onclick="alert(1)">ok</p>', 'onclick'],
            'style attribute' => ['<p style="color:red">ok</p>', 'style'],
            'javascript href' => ['<p><a href="javascript:alert(1)">x</a></p>', 'javascript'],
            'data href' => ['<p><a href="data:text/html;base64,PHNjcmlwdD4=">x</a></p>', 'data:'],
            'entity obfuscated javascript' => ['<p><a href="&#106;avascript:alert(1)">x</a></p>', 'avascript'],
            'img' => ['<p>ok</p><img src="x" onerror="alert(1)">', '<img'],
            'iframe' => ['<iframe src="https://evil.example"></iframe><p>ok</p>', 'iframe'],
            'heading handler' => ['<h2 onclick="alert(1)">big</h2>', 'onclick'],
            'heading script' => ['<h3>big<script>alert(1)</script></h3>', 'script'],
            'heading style attribute' => ['<h1 style="color:red">big</h1>', 'style'],
            'heading img' => ['<h4><img src="x" onerror="alert(1)">big</h4>', '<img'],
            'blockquote handler' => ['<blockquote onmouseover="alert(1)">q</blockquote>', 'onmouseover'],
            'blockquote script' => ['<blockquote>q<script>alert(1)</script></blockquote>', 'script'],
            'blockquote cite javascript' => ['<blockquote cite="javascript:alert(1)">q</blockquote>', 'javascript'],
            'heading javascript link' => ['<h2><a href="javascript:alert(1)">x</a></h2>', 'javascript'],
            'style tag' => ['<style>body{display:none}</style><p>ok</p>', 'style'],
        ];
    }

    #[DataProvider('dangerousMarkup')]
    public function test_dangerous_markup_is_stripped(string $html, string $needle): void
    {
        $this->assertStringNotContainsString($needle, $this->sanitizer()->sanitize($html));
    }

    public function test_output_is_idempotent(): void
    {
        $once = $this->sanitizer()->sanitize('<div>Hi <b>there</b> <a href="https://example.com">x</a><script>1</script></div><ul><li>a</li></ul>');

        $this->assertSame($once, $this->sanitizer()->sanitize($once));
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', $this->sanitizer()->sanitize(''));
        $this->assertSame('', $this->sanitizer()->sanitize("  \n"));
    }

    public function test_trix_quote_markup_is_kept(): void
    {
        $html = '<blockquote><div>Ein Zitat</div></blockquote>';

        $this->assertSame($html, $this->sanitizer()->sanitize($html));
    }

    public function test_empty_headings_and_quotes_are_removed(): void
    {
        $this->assertSame('', $this->sanitizer()->sanitize('<h2></h2><blockquote></blockquote>'));
    }

    public function test_empty_elements_are_removed(): void
    {
        $this->assertSame('', $this->sanitizer()->sanitize('<p></p>'));
    }

    public function test_the_allowed_tags_follow_the_config(): void
    {
        config()->set('pagebuilder.sanitizer.allowed', 'p');
        $this->app->forgetInstance(HtmlSanitizer::class);

        $this->assertSame('<p>a b</p>', $this->sanitizer()->sanitize('<p>a <strong>b</strong></p>'));
    }
}
