<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Feature;

use Dandrum\PageBuilder\Contracts\ImageMediaResolver;
use Dandrum\PageBuilder\Tests\Fixtures\ArrayMediaResolver;
use Dandrum\PageBuilder\Tests\Fixtures\EditorHost;
use Dandrum\PageBuilder\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

final class RenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ArrayMediaResolver::reset();

        // Outside a Livewire render / the web middleware group nothing shares `$errors`.
        view()->share('errors', new ViewErrorBag);
    }

    private function useMediaSource(?string $renderView = null): void
    {
        config()->set('pagebuilder.image', [
            'source' => 'media',
            'render_view' => $renderView,
        ]);

        $this->app->singleton(ImageMediaResolver::class, ArrayMediaResolver::class);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function render(array $document): string
    {
        return Blade::render('<x-pagebuilder::render :blocks="$doc" />', ['doc' => $document]);
    }

    public function test_it_renders_outside_livewire_and_outputs_the_stored_html_raw(): void
    {
        $html = $this->render(['version' => 1, 'blocks' => [
            ['id' => 'a', 'type' => 'paragraph', 'data' => ['html' => '<p>Hallo <strong>Welt</strong></p>', 'align' => 'center']],
        ]]);

        $this->assertStringContainsString('<p>Hallo <strong>Welt</strong></p>', $html);
        $this->assertStringContainsString('pb-block--paragraph', $html);
        $this->assertStringContainsString('pb-align-center', $html);
    }

    public function test_alignment_classes_vanish_when_alignment_is_off(): void
    {
        config()->set('pagebuilder.alignment', false);

        $html = $this->render(['blocks' => [
            ['id' => 'a', 'type' => 'paragraph', 'data' => ['html' => '<p>x</p>', 'align' => 'center']],
        ]]);

        $this->assertStringNotContainsString('pb-align-', $html);
    }

    public function test_it_accepts_json_and_a_bare_blocks_array(): void
    {
        $block = ['id' => 'a', 'type' => 'paragraph', 'data' => ['html' => '<p>JSON</p>']];

        $this->assertStringContainsString('JSON', Blade::render('<x-pagebuilder::render :blocks="$doc" />', ['doc' => json_encode(['blocks' => [$block]])]));
        $this->assertStringContainsString('JSON', $this->render([$block]));
    }

    public function test_null_and_empty_documents_render_nothing(): void
    {
        $this->assertSame('', trim(Blade::render('<x-pagebuilder::render :blocks="null" />')));
        $this->assertSame('', trim($this->render(['blocks' => []])));
    }

    public function test_unknown_and_malformed_blocks_are_skipped(): void
    {
        $html = $this->render(['blocks' => [
            ['id' => 'a', 'type' => 'quote', 'data' => ['html' => '<p>nope</p>']],
            'garbage',
            ['id' => 'b', 'type' => 'paragraph', 'data' => ['html' => '<p>da</p>']],
        ]]);

        $this->assertStringNotContainsString('nope', $html);
        $this->assertStringContainsString('<p>da</p>', $html);
    }

    public function test_a_url_image_renders_with_alt_and_caption(): void
    {
        $html = $this->render(['blocks' => [
            ['id' => 'a', 'type' => 'image', 'data' => ['url' => '/x.jpg', 'alt' => 'Alt "text"', 'caption' => 'Unterschrift']],
        ]]);

        $this->assertStringContainsString('src="/x.jpg"', $html);
        $this->assertStringContainsString('alt="Alt &quot;text&quot;"', $html);
        $this->assertStringContainsString('<figcaption>Unterschrift</figcaption>', $html);
    }

    public function test_a_media_image_uses_the_resolver_metadata_and_caption_override(): void
    {
        $this->useMediaSource();

        $inherited = $this->render(['blocks' => [['id' => 'a', 'type' => 'image', 'data' => ['media_id' => 1]]]]);
        $overridden = $this->render(['blocks' => [['id' => 'a', 'type' => 'image', 'data' => ['media_id' => 1, 'caption' => 'Eigene']]]]);

        $this->assertStringContainsString('src="/m/1.jpg"', $inherited);
        $this->assertStringContainsString('alt="Rathaus"', $inherited);
        $this->assertStringContainsString('Das Rathaus', $inherited);
        $this->assertStringContainsString('Max Muster', $inherited);
        $this->assertStringContainsString('Eigene', $overridden);
        $this->assertStringNotContainsString('Das Rathaus', $overridden);
    }

    public function test_a_missing_media_renders_nothing(): void
    {
        $this->useMediaSource();

        $html = $this->render(['blocks' => [['id' => 'a', 'type' => 'image', 'data' => ['media_id' => 404]]]]);

        $this->assertStringNotContainsString('<figure', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_a_media_without_a_preview_url_renders_nothing(): void
    {
        $this->useMediaSource();

        $html = $this->render(['blocks' => [['id' => 'a', 'type' => 'image', 'data' => ['media_id' => 2]]]]);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_the_resolver_is_called_once_for_several_image_blocks(): void
    {
        $this->useMediaSource();

        $this->render(['blocks' => [
            ['id' => 'a', 'type' => 'image', 'data' => ['media_id' => 1]],
            ['id' => 'b', 'type' => 'image', 'data' => ['media_id' => 2]],
            ['id' => 'c', 'type' => 'image', 'data' => ['media_id' => 1]],
        ]]);

        $this->assertSame(1, ArrayMediaResolver::$calls);
    }

    public function test_the_resolver_is_not_called_without_image_blocks(): void
    {
        $this->useMediaSource();

        $this->render(['blocks' => [['id' => 'a', 'type' => 'paragraph', 'data' => ['html' => '<p>x</p>']]]]);

        $this->assertSame(0, ArrayMediaResolver::$calls);
    }

    public function test_a_configured_render_view_replaces_the_default_image_markup(): void
    {
        $this->app['view']->addNamespace('testviews', __DIR__.'/../views');
        $this->useMediaSource('testviews::custom-image');

        $html = $this->render(['blocks' => [['id' => 'a', 'type' => 'image', 'data' => ['media_id' => 1, 'caption' => 'K']]]]);

        $this->assertStringContainsString('custom:K:Rathaus', $html);
        $this->assertStringNotContainsString('<figure', $html);
    }

    public function test_the_editor_renders_with_handles_and_labelled_buttons(): void
    {
        $id = '11111111-1111-4111-8111-111111111111';

        $html = Livewire::test(EditorHost::class, ['initial' => ['blocks' => [
            ['id' => $id, 'type' => 'paragraph', 'data' => ['html' => '<p>x</p>']],
            ['id' => '22222222-2222-4222-8222-222222222222', 'type' => 'image', 'data' => []],
        ]]])->html();

        $editor = Blade::render('<x-pagebuilder::editor :blocks="$blocks" :block-types="$types" :context="$context" />', [
            'blocks' => [
                ['id' => $id, 'type' => 'paragraph', 'data' => ['html' => '<p>x</p>', 'align' => 'left']],
                ['id' => '22222222-2222-4222-8222-222222222222', 'type' => 'image', 'data' => ['media_id' => null, 'url' => '', 'alt' => '', 'caption' => '', 'credit' => '']],
            ],
            'types' => [['key' => 'paragraph', 'label' => 'Paragraph'], ['key' => 'image', 'label' => 'Image']],
            'context' => [],
        ]);

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('wire:sort="sortPageBuilderBlock"', $editor);
        $this->assertStringContainsString('wire:sort:handle', $editor);
        $this->assertStringContainsString('wire:sort:item="'.$id.'"', $editor);
        $this->assertStringContainsString('aria-label="Move Paragraph 1 up"', $editor);
        $this->assertStringContainsString('aria-label="Insert Image after block 2"', $editor);
        $this->assertStringContainsString('aria-label="Insert Paragraph at the start"', $editor);
        $this->assertStringContainsString('<trix-toolbar id="pb-'.$id.'-toolbar"', $editor);
        $this->assertStringNotContainsString('@entangle', $editor);
        $this->assertStringNotContainsString('data-trix-attribute="heading1"', $editor);
        $this->assertStringContainsString('wire:model.live="pageBuilderBlocks.0.data.align"', $editor);
    }

    public function test_the_editor_hides_alignment_when_disabled(): void
    {
        config()->set('pagebuilder.alignment', false);

        $editor = Blade::render('<x-pagebuilder::editor :blocks="$blocks" :block-types="[]" />', [
            'blocks' => [['id' => '11111111-1111-4111-8111-111111111111', 'type' => 'paragraph', 'data' => ['html' => '', 'align' => 'left']]],
        ]);

        $this->assertStringNotContainsString('data.align', $editor);
    }

    public function test_the_image_editor_shows_the_picker_only_when_enabled(): void
    {
        $block = ['id' => '11111111-1111-4111-8111-111111111111', 'type' => 'image', 'data' => ['media_id' => null]];

        $with = Blade::render('<x-pagebuilder::editor :blocks="[$block]" :block-types="[]" :context="$context" />', [
            'block' => $block,
            'context' => ['options' => ['source' => 'media', 'picker' => true, 'upload' => 'deferred']],
        ]);
        $without = Blade::render('<x-pagebuilder::editor :blocks="[$block]" :block-types="[]" :context="$context" />', [
            'block' => $block,
            'context' => ['options' => ['source' => 'media', 'picker' => false, 'upload' => 'off', 'notice' => 'Erst Lokalseite wählen.']],
        ]);

        $this->assertStringContainsString('pagebuilder-pick-image', $with);
        $this->assertStringContainsString('type="file"', $with);
        $this->assertStringNotContainsString('pagebuilder-pick-image', $without);
        $this->assertStringNotContainsString('type="file"', $without);
        $this->assertStringContainsString('Erst Lokalseite wählen.', $without);
    }
}
