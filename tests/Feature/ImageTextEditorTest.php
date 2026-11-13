<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Feature;

use Dandrum\PageBuilder\Contracts\ImageMediaResolver;
use Dandrum\PageBuilder\Tests\Fixtures\ArrayMediaResolver;
use Dandrum\PageBuilder\Tests\Fixtures\EditorHost;
use Dandrum\PageBuilder\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

final class ImageTextEditorTest extends TestCase
{
    private const A = '11111111-1111-4111-8111-111111111111';

    private const B = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();

        EditorHost::$stored = [];
        ArrayMediaResolver::reset();
        view()->share('errors', new ViewErrorBag);
    }

    /**
     * @param  array<string, mixed>  $image
     */
    private function useMediaSource(array $image = []): void
    {
        config()->set('pagebuilder.image', array_replace([
            'source' => 'media',
            'upload' => 'deferred',
            'picker' => true,
            'upload_required' => ['alt', 'caption', 'credit'],
        ], $image));

        $this->app->singleton(ImageMediaResolver::class, ArrayMediaResolver::class);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{initial: array<string, mixed>}
     */
    private function host(array $data = []): array
    {
        return ['initial' => ['blocks' => [
            ['id' => self::A, 'type' => 'image_text', 'data' => $data + ['html' => '<div>Text</div>', 'imagePosition' => 'right']],
        ]]];
    }

    public function test_it_can_be_inserted_with_its_defaults(): void
    {
        $component = Livewire::test(EditorHost::class)->call('insertPageBuilderBlock', 'image_text');

        $data = $component->get('pageBuilderBlocks.0.data');

        $this->assertSame('left', $data['imagePosition']);
        $this->assertNull($data['media_id']);
        $this->assertSame('', $data['html']);
    }

    public function test_old_documents_get_the_new_defaults_merged_in(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => ['blocks' => [
            ['id' => self::A, 'type' => 'image_text', 'data' => ['imageUrl' => '/a.jpg', 'imagePosition' => 'right', 'html' => '<div>x</div>']],
        ]]]);

        $this->assertSame('/a.jpg', $component->get('pageBuilderBlocks.0.data.imageUrl'));
        $this->assertSame('', $component->get('pageBuilderBlocks.0.data.alt'));
        $this->assertNull($component->get('pageBuilderBlocks.0.data.media_id'));
    }

    public function test_picking_sets_the_media_and_keeps_text_and_position(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host(['caption' => 'alt']))
            ->dispatch('pagebuilder-image-picked', blockId: self::A, mediaId: 2);

        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.media_id'));
        $this->assertSame('', $component->get('pageBuilderBlocks.0.data.caption'));
        $this->assertSame('right', $component->get('pageBuilderBlocks.0.data.imagePosition'));
        $this->assertSame('<div>Text</div>', $component->get('pageBuilderBlocks.0.data.html'));
        $component->assertHasNoErrors();
    }

    public function test_picking_an_unknown_media_id_is_an_error(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host(['media_id' => 1]))
            ->dispatch('pagebuilder-image-picked', blockId: self::A, mediaId: 404);

        $this->assertSame(1, $component->get('pageBuilderBlocks.0.data.media_id'));
        $component->assertHasErrors(['pageBuilderBlocks.0.data.media_id']);
    }

    public function test_clearing_the_image_keeps_text_and_position(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host(['media_id' => 1]))
            ->call('clearPageBuilderImage', self::A);

        $this->assertNull($component->get('pageBuilderBlocks.0.data.media_id'));
        $this->assertSame('<div>Text</div>', $component->get('pageBuilderBlocks.0.data.html'));
        $this->assertSame('right', $component->get('pageBuilderBlocks.0.data.imagePosition'));
    }

    public function test_a_deferred_upload_waits_and_is_stored_without_losing_the_text(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.'.self::A, UploadedFile::fake()->image('bild.jpg'));

        $this->assertSame([], EditorHost::$stored);
        $this->assertArrayHasKey(self::A, $component->get('pageBuilderUploads'));

        $component->call('storePending');

        $this->assertSame(['bild.jpg'], EditorHost::$stored);
        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.media_id'));
        $this->assertSame('<div>Text</div>', $component->get('pageBuilderBlocks.0.data.html'));
        $this->assertSame('right', $component->get('pageBuilderBlocks.0.data.imagePosition'));
        $this->assertSame([], $component->get('pageBuilderUploads'));
    }

    public function test_an_immediate_upload_stores_right_away(): void
    {
        $this->useMediaSource(['upload' => 'immediate']);

        $component = Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.'.self::A, UploadedFile::fake()->image('sofort.jpg'));

        $this->assertSame(['sofort.jpg'], EditorHost::$stored);
        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.media_id'));
    }

    public function test_a_tampered_upload_is_ignored_when_uploads_are_off(): void
    {
        $this->useMediaSource(['upload' => 'off']);

        $component = Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.'.self::A, UploadedFile::fake()->image('a.jpg'));

        $this->assertSame([], $component->get('pageBuilderUploads'));
        $this->assertSame([], EditorHost::$stored);
    }

    public function test_the_rules_require_an_image_or_an_upload_with_metadata(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, $this->host())
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.media_id' => 'required']);

        Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.'.self::A, UploadedFile::fake()->image('a.jpg'))
            ->call('validateAll')
            ->assertHasErrors([
                'pageBuilderBlocks.0.data.alt' => 'required',
                'pageBuilderBlocks.0.data.caption' => 'required',
                'pageBuilderBlocks.0.data.credit' => 'required',
            ])
            ->assertHasNoErrors(['pageBuilderBlocks.0.data.media_id']);

        Livewire::test(EditorHost::class, $this->host(['alt' => 'a', 'caption' => 'c', 'credit' => 'p']))
            ->set('pageBuilderUploads.'.self::A, UploadedFile::fake()->image('a.jpg'))
            ->call('validateAll')
            ->assertHasNoErrors();
    }

    public function test_the_rules_reject_a_foreign_media_and_a_bad_position(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, $this->host(['media_id' => 404]))
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.media_id']);

        Livewire::test(EditorHost::class, $this->host(['media_id' => 1, 'imagePosition' => 'top']))
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.imagePosition']);
    }

    public function test_the_url_source_requires_image_url_and_alt(): void
    {
        Livewire::test(EditorHost::class, $this->host())
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.imageUrl' => 'required', 'pageBuilderBlocks.0.data.alt' => 'required']);
    }

    public function test_the_editor_context_resolves_the_preview_for_image_text_blocks(): void
    {
        $this->useMediaSource();

        $context = Livewire::test(EditorHost::class, $this->host(['media_id' => 1]))
            ->instance()->pageBuilderEditorContext();

        $this->assertSame('Rathaus', $context['previews'][self::A]['alt']);
        $this->assertSame('/m/1.jpg', $context['previews'][self::A]['url']);
    }

    public function test_the_editor_shows_position_picker_alt_caption_credit_row_and_text_editor(): void
    {
        $block = ['id' => self::A, 'type' => 'image_text', 'data' => ['media_id' => null, 'imagePosition' => 'right', 'html' => '']];

        $html = Blade::render('<x-pagebuilder::editor :blocks="[$block]" :block-types="[]" :context="$context" />', [
            'block' => $block,
            'context' => ['options' => ['source' => 'media', 'picker' => true, 'upload' => 'deferred']],
        ]);

        $this->assertStringContainsString('wire:model.live="pageBuilderBlocks.0.data.imagePosition"', $html);
        $this->assertStringContainsString('pb-image-text-edit--right', $html);
        $this->assertStringContainsString('pagebuilder-pick-image', $html);
        $this->assertStringContainsString('pageBuilderUploads.'.self::A, $html);
        $this->assertStringContainsString('<trix-toolbar id="pb-'.self::A.'-toolbar"', $html);
        $this->assertStringContainsString('Upload an image or choose one from the library', $html);
    }

    public function test_the_picked_state_shows_alt_caption_and_credit_in_one_row(): void
    {
        $block = ['id' => self::A, 'type' => 'image', 'data' => ['media_id' => 1, 'caption' => 'Eigene']];

        $html = Blade::render('<x-pagebuilder::editor :blocks="[$block]" :block-types="[]" :context="$context" />', [
            'block' => $block,
            'context' => [
                'options' => ['source' => 'media', 'picker' => true],
                'previews' => [self::A => ['url' => '/m/1.jpg', 'alt' => 'Rathaus', 'caption' => 'Das Rathaus', 'credit' => 'Max Muster']],
            ],
        ]);

        $this->assertSame(1, substr_count($html, 'class="pb-meta-row '));
        $this->assertStringContainsString('value="Rathaus"', $html);
        $this->assertStringContainsString('value="Max Muster"', $html);
        $this->assertStringContainsString('wire:model.blur="pageBuilderBlocks.0.data.caption"', $html);
        $this->assertStringNotContainsString('wire:model.blur="pageBuilderBlocks.0.data.alt"', $html);
    }

    public function test_the_rich_text_toolbar_offers_headings_one_to_six_and_a_quote(): void
    {
        $block = ['id' => self::A, 'type' => 'paragraph', 'data' => ['html' => '', 'align' => 'left']];

        $html = Blade::render('<x-pagebuilder::editor :blocks="[$block]" :block-types="[]" />', ['block' => $block]);

        foreach (range(1, 6) as $level) {
            $this->assertStringContainsString('data-trix-attribute="heading'.$level.'"', $html);
        }

        $this->assertStringContainsString('data-trix-attribute="quote"', $html);
        $this->assertStringContainsString('data-trix-attribute="bullet"', $html);
        $this->assertStringContainsString('data-trix-attribute="number"', $html);
        $this->assertStringContainsString("blocks['heading' + level]", $html);
    }

    public function test_the_insert_row_offers_image_text(): void
    {
        $html = Blade::render('<x-pagebuilder::editor :blocks="[]" :block-types="$types" />', [
            'types' => [['key' => 'image_text', 'label' => 'Image + Text']],
        ]);

        $this->assertStringContainsString("insertPageBuilderBlock('image_text', '')", $html);
        $this->assertStringContainsString('aria-label="Insert Image + Text at the start"', $html);
    }

    public function test_the_render_shows_image_and_text_and_flips_for_the_right_position(): void
    {
        $this->useMediaSource();

        $left = Blade::render('<x-pagebuilder::render :blocks="$doc" />', ['doc' => ['blocks' => [
            ['id' => 'a', 'type' => 'image_text', 'data' => ['media_id' => 1, 'imagePosition' => 'left', 'html' => '<h2>Titel</h2><blockquote>Zitat</blockquote>']],
        ]]]);
        $right = Blade::render('<x-pagebuilder::render :blocks="$doc" />', ['doc' => ['blocks' => [
            ['id' => 'a', 'type' => 'image_text', 'data' => ['media_id' => 1, 'caption' => 'Eigene', 'imagePosition' => 'right', 'html' => '<div>Text</div>']],
        ]]]);

        $this->assertStringContainsString('src="/m/1.jpg"', $left);
        $this->assertStringContainsString('alt="Rathaus"', $left);
        $this->assertStringContainsString('<h2>Titel</h2>', $left);
        $this->assertStringContainsString('<blockquote>Zitat</blockquote>', $left);
        $this->assertStringNotContainsString('pb-block--image-right', $left);
        $this->assertStringContainsString('pb-block--image-right', $right);
        $this->assertStringContainsString('Eigene', $right);
    }

    public function test_the_render_hook_takes_over_the_image_side(): void
    {
        $this->app['view']->addNamespace('testviews', __DIR__.'/../views');
        $this->useMediaSource();
        config()->set('pagebuilder.image.render_view', 'testviews::custom-image');

        $html = Blade::render('<x-pagebuilder::render :blocks="$doc" />', ['doc' => ['blocks' => [
            ['id' => 'a', 'type' => 'image_text', 'data' => ['media_id' => 1, 'caption' => 'K', 'html' => '<div>Text</div>']],
        ]]]);

        $this->assertStringContainsString('custom:K:Rathaus', $html);
        $this->assertStringContainsString('<div>Text</div>', $html);
    }

    public function test_the_url_source_renders_the_image_url_with_alt(): void
    {
        $html = Blade::render('<x-pagebuilder::render :blocks="$doc" />', ['doc' => ['blocks' => [
            ['id' => 'a', 'type' => 'image_text', 'data' => ['imageUrl' => '/x.jpg', 'alt' => 'Alt', 'html' => '<div>Text</div>']],
        ]]]);

        $this->assertStringContainsString('src="/x.jpg"', $html);
        $this->assertStringContainsString('alt="Alt"', $html);
    }

    public function test_a_missing_media_still_renders_the_text(): void
    {
        $this->useMediaSource();

        $html = Blade::render('<x-pagebuilder::render :blocks="$doc" />', ['doc' => ['blocks' => [
            ['id' => 'a', 'type' => 'image_text', 'data' => ['media_id' => 404, 'html' => '<div>Text</div>']],
        ]]]);

        $this->assertStringContainsString('<div>Text</div>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}
