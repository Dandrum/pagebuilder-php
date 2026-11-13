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

final class GalleryEditorTest extends TestCase
{
    private const G = '99999999-9999-4999-8999-999999999999';

    private const I1 = '11111111-1111-4111-8111-111111111111';

    private const I2 = '22222222-2222-4222-8222-222222222222';

    private const I3 = '33333333-3333-4333-8333-333333333333';

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
     * @param  list<array<string, mixed>>|null  $items
     * @return array{initial: array<string, mixed>}
     */
    private function host(?array $items = null, bool $slideshow = false): array
    {
        $items ??= [
            ['id' => self::I1, 'media_id' => 1, 'caption' => ''],
            ['id' => self::I2, 'media_id' => 2, 'caption' => 'Zwei'],
        ];

        return ['initial' => ['blocks' => [
            ['id' => self::G, 'type' => 'gallery', 'data' => ['slideshow' => $slideshow, 'items' => $items]],
        ]]];
    }

    /**
     * @return list<string>
     */
    private function itemIds(mixed $component): array
    {
        return array_column($component->get('pageBuilderBlocks.0.data.items'), 'id');
    }

    public function test_it_can_be_inserted_with_its_defaults(): void
    {
        $component = Livewire::test(EditorHost::class)->call('insertPageBuilderBlock', 'gallery');

        $this->assertSame('gallery', $component->get('pageBuilderBlocks.0.type'));
        $this->assertFalse($component->get('pageBuilderBlocks.0.data.slideshow'));
        $this->assertSame([], $component->get('pageBuilderBlocks.0.data.items'));
    }

    public function test_the_block_type_menu_lists_the_gallery(): void
    {
        $keys = array_column(Livewire::test(EditorHost::class)->instance()->pageBuilderBlockTypes(), 'key');

        $this->assertContains('gallery', $keys);
    }

    public function test_images_can_be_added_as_empty_slots_with_unique_ids(): void
    {
        $component = Livewire::test(EditorHost::class, $this->host([]))
            ->call('addPageBuilderGalleryItem', self::G)
            ->call('addPageBuilderGalleryItem', self::G);

        $items = $component->get('pageBuilderBlocks.0.data.items');

        $this->assertCount(2, $items);
        $this->assertNotSame($items[0]['id'], $items[1]['id']);
        $this->assertNull($items[0]['media_id']);
        $this->assertSame('', $items[0]['url']);
    }

    public function test_adding_beyond_the_maximum_does_nothing(): void
    {
        config()->set('pagebuilder.gallery.max_images', 2);

        $component = Livewire::test(EditorHost::class, $this->host())
            ->call('addPageBuilderGalleryItem', self::G);

        $this->assertCount(2, $component->get('pageBuilderBlocks.0.data.items'));
    }

    public function test_adding_to_an_unknown_or_non_gallery_block_does_nothing(): void
    {
        $component = Livewire::test(EditorHost::class, ['initial' => ['blocks' => [
            ['id' => self::G, 'type' => 'paragraph', 'data' => ['html' => '<p>x</p>']],
        ]]])->call('addPageBuilderGalleryItem', self::G)->call('addPageBuilderGalleryItem', 'unbekannt');

        $this->assertArrayNotHasKey('items', $component->get('pageBuilderBlocks.0.data'));
    }

    public function test_items_move_up_and_down_and_stop_at_the_edges(): void
    {
        $component = Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'media_id' => 1],
            ['id' => self::I2, 'media_id' => 2],
            ['id' => self::I3, 'media_id' => 1],
        ]));

        $component->call('movePageBuilderGalleryItem', self::G, self::I3, -1);
        $this->assertSame([self::I1, self::I3, self::I2], $this->itemIds($component));

        $component->call('movePageBuilderGalleryItem', self::G, self::I1, -1);
        $this->assertSame([self::I1, self::I3, self::I2], $this->itemIds($component));

        $component->call('movePageBuilderGalleryItem', self::G, self::I1, 1);
        $this->assertSame([self::I3, self::I1, self::I2], $this->itemIds($component));

        $component->call('movePageBuilderGalleryItem', self::G, self::I2, 1);
        $this->assertSame([self::I3, self::I1, self::I2], $this->itemIds($component));

        $component->call('movePageBuilderGalleryItem', self::G, 'unbekannt', 1);
        $this->assertSame([self::I3, self::I1, self::I2], $this->itemIds($component));
    }

    public function test_an_item_can_be_removed_together_with_its_pending_upload(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.'.self::I2, UploadedFile::fake()->image('a.jpg'));

        $this->assertArrayHasKey(self::I2, $component->get('pageBuilderUploads'));

        $component->call('removePageBuilderGalleryItem', self::G, self::I2);

        $this->assertSame([self::I1], $this->itemIds($component));
        $this->assertSame([], $component->get('pageBuilderUploads'));
    }

    public function test_picking_fills_only_the_item_and_keeps_its_id_and_caption_override(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'media_id' => null, 'url' => '', 'alt' => '', 'caption' => '', 'credit' => ''],
            ['id' => self::I2, 'media_id' => 2, 'caption' => 'Zwei'],
        ]))->dispatch('pagebuilder-image-picked', blockId: self::I1, mediaId: 1);

        $this->assertSame(1, $component->get('pageBuilderBlocks.0.data.items.0.media_id'));
        $this->assertSame(self::I1, $component->get('pageBuilderBlocks.0.data.items.0.id'));
        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.items.1.media_id'));
        $this->assertSame('Zwei', $component->get('pageBuilderBlocks.0.data.items.1.caption'));
        $this->assertSame(self::G, $component->get('pageBuilderBlocks.0.id'));
        $component->assertHasNoErrors();
    }

    public function test_picking_an_unknown_media_is_an_error_on_the_item(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host())
            ->dispatch('pagebuilder-image-picked', blockId: self::I2, mediaId: 404);

        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.items.1.media_id'));
        $component->assertHasErrors(['pageBuilderBlocks.0.data.items.1.media_id']);
    }

    public function test_the_gallery_block_id_itself_is_not_a_pick_target(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host())
            ->dispatch('pagebuilder-image-picked', blockId: self::G, mediaId: 1);

        $this->assertArrayNotHasKey('media_id', $component->get('pageBuilderBlocks.0.data'));
    }

    public function test_clearing_an_item_keeps_the_slot_and_its_id(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host())
            ->call('clearPageBuilderImage', self::I1);

        $this->assertNull($component->get('pageBuilderBlocks.0.data.items.0.media_id'));
        $this->assertSame(self::I1, $component->get('pageBuilderBlocks.0.data.items.0.id'));
        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.items.1.media_id'));
    }

    public function test_a_deferred_upload_is_stored_into_its_item_and_the_metadata_is_reset(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'media_id' => null, 'url' => '', 'alt' => 'A', 'caption' => 'C', 'credit' => 'P'],
            ['id' => self::I2, 'media_id' => 1],
        ]))->set('pageBuilderUploads.'.self::I1, UploadedFile::fake()->image('neu.jpg'));

        $this->assertSame([], EditorHost::$stored);

        $component->call('storePending');

        $this->assertSame(['neu.jpg'], EditorHost::$stored);
        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.items.0.media_id'));
        $this->assertSame('', $component->get('pageBuilderBlocks.0.data.items.0.alt'));
        $this->assertSame('', $component->get('pageBuilderBlocks.0.data.items.0.credit'));
        $this->assertSame(self::I1, $component->get('pageBuilderBlocks.0.data.items.0.id'));
        $this->assertSame(1, $component->get('pageBuilderBlocks.0.data.items.1.media_id'));
        $this->assertSame([], $component->get('pageBuilderUploads'));
    }

    public function test_an_immediate_upload_stores_right_away_into_the_item(): void
    {
        $this->useMediaSource(['upload' => 'immediate']);

        $component = Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.'.self::I1, UploadedFile::fake()->image('sofort.jpg'));

        $this->assertSame(['sofort.jpg'], EditorHost::$stored);
        $this->assertSame(2, $component->get('pageBuilderBlocks.0.data.items.0.media_id'));
    }

    public function test_a_tampered_upload_is_ignored_when_uploads_are_off_or_the_id_is_unknown(): void
    {
        $this->useMediaSource(['upload' => 'off']);

        $component = Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.'.self::I1, UploadedFile::fake()->image('a.jpg'));

        $this->assertSame([], $component->get('pageBuilderUploads'));

        $this->useMediaSource(['upload' => 'deferred']);

        $component = Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.44444444-4444-4444-8444-444444444444', UploadedFile::fake()->image('b.jpg'));

        $this->assertSame([], $component->get('pageBuilderUploads'));
        $this->assertSame([], EditorHost::$stored);
    }

    public function test_removing_the_block_drops_the_uploads_of_all_its_items(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderUploads.'.self::I1, UploadedFile::fake()->image('a.jpg'))
            ->set('pageBuilderUploads.'.self::I2, UploadedFile::fake()->image('b.jpg'))
            ->call('removePageBuilderBlock', self::G);

        $this->assertSame([], $component->get('pageBuilderUploads'));
        $this->assertSame([], $component->get('pageBuilderBlocks'));
    }

    public function test_duplicating_gives_the_copy_new_item_ids(): void
    {
        $component = Livewire::test(EditorHost::class, $this->host())
            ->call('duplicatePageBuilderBlock', self::G);

        $first = array_column($component->get('pageBuilderBlocks.0.data.items'), 'id');
        $copy = array_column($component->get('pageBuilderBlocks.1.data.items'), 'id');

        $this->assertCount(2, $copy);
        $this->assertSame([], array_intersect($first, $copy));
        $this->assertSame(
            array_column($component->get('pageBuilderBlocks.0.data.items'), 'media_id'),
            array_column($component->get('pageBuilderBlocks.1.data.items'), 'media_id'),
        );
    }

    public function test_the_editor_context_resolves_previews_per_item_with_one_lookup(): void
    {
        $this->useMediaSource();

        $context = Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'media_id' => 1],
            ['id' => self::I2, 'media_id' => 2],
            ['id' => self::I3, 'media_id' => 404],
        ]))->instance()->pageBuilderEditorContext();

        $this->assertSame(1, ArrayMediaResolver::$calls);
        $this->assertSame('Rathaus', $context['previews'][self::I1]['alt']);
        $this->assertSame('Brücke', $context['previews'][self::I2]['alt']);
        $this->assertNull($context['previews'][self::I3]);
        $this->assertSame(30, $context['galleryMax']);
    }

    public function test_the_rules_require_an_image_or_an_upload_with_metadata_per_item(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'media_id' => 1],
            ['id' => self::I2, 'media_id' => null, 'url' => '', 'alt' => '', 'caption' => '', 'credit' => ''],
        ]))
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.items.1.media_id' => 'required'])
            ->assertHasNoErrors(['pageBuilderBlocks.0.data.items.0.media_id']);

        Livewire::test(EditorHost::class, $this->host([['id' => self::I1, 'media_id' => null, 'alt' => '', 'caption' => '', 'credit' => '']]))
            ->set('pageBuilderUploads.'.self::I1, UploadedFile::fake()->image('a.jpg'))
            ->call('validateAll')
            ->assertHasErrors([
                'pageBuilderBlocks.0.data.items.0.alt' => 'required',
                'pageBuilderBlocks.0.data.items.0.caption' => 'required',
                'pageBuilderBlocks.0.data.items.0.credit' => 'required',
            ])
            ->assertHasNoErrors(['pageBuilderBlocks.0.data.items.0.media_id']);

        Livewire::test(EditorHost::class, $this->host([['id' => self::I1, 'media_id' => null, 'alt' => 'a', 'caption' => 'c', 'credit' => 'p']]))
            ->set('pageBuilderUploads.'.self::I1, UploadedFile::fake()->image('a.jpg'))
            ->call('validateAll')
            ->assertHasNoErrors();
    }

    public function test_a_valid_gallery_passes_and_an_unknown_media_fails_on_the_item_key(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, $this->host())->call('validateAll')->assertHasNoErrors();

        Livewire::test(EditorHost::class, $this->host([['id' => self::I1, 'media_id' => 404]]))
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.items.0.media_id']);
    }

    public function test_more_images_than_the_maximum_are_rejected(): void
    {
        $this->useMediaSource();
        config()->set('pagebuilder.gallery.max_images', 2);

        Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'media_id' => 1],
            ['id' => self::I2, 'media_id' => 2],
            ['id' => self::I3, 'media_id' => 1],
        ]))
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.items' => 'max']);
    }

    public function test_duplicate_item_ids_and_a_manipulated_slideshow_flag_are_rejected(): void
    {
        $this->useMediaSource();

        Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'media_id' => 1],
            ['id' => self::I1, 'media_id' => 2],
        ]))
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.items.0.id', 'pageBuilderBlocks.0.data.items.1.id']);

        Livewire::test(EditorHost::class, $this->host())
            ->set('pageBuilderBlocks.0.data.slideshow', 'abc')
            ->call('validateAll')
            ->assertHasErrors(['pageBuilderBlocks.0.data.slideshow']);
    }

    public function test_the_url_source_requires_url_and_alt_per_item(): void
    {
        Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'url' => '', 'alt' => ''],
        ]))
            ->call('validateAll')
            ->assertHasErrors([
                'pageBuilderBlocks.0.data.items.0.url' => 'required',
                'pageBuilderBlocks.0.data.items.0.alt' => 'required',
            ]);

        Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'url' => '/a.jpg', 'alt' => 'A'],
        ]))->call('validateAll')->assertHasNoErrors();
    }

    public function test_the_messages_name_the_limit(): void
    {
        config()->set('pagebuilder.gallery.max_images', 7);

        $messages = Livewire::test(EditorHost::class, $this->host())->instance()->pageBuilderMessages();

        $this->assertSame('A gallery may contain at most 7 images.', $messages['pageBuilderBlocks.*.data.items.max']);
        $this->assertArrayHasKey('pageBuilderBlocks.*.data.items.*.media_id.required', $messages);
    }

    public function test_the_normalized_document_drops_empty_slots_and_an_empty_gallery(): void
    {
        $this->useMediaSource();

        $component = Livewire::test(EditorHost::class, $this->host([
            ['id' => self::I1, 'media_id' => 1, 'caption' => 'x'],
            ['id' => self::I2, 'media_id' => null, 'url' => '', 'alt' => '', 'caption' => '', 'credit' => ''],
        ], true));

        $document = $component->instance()->getPageBuilderDocumentArray();

        $this->assertSame(
            ['slideshow' => true, 'items' => [['id' => self::I1, 'media_id' => 1, 'caption' => 'x']]],
            $document['blocks'][0]['data'],
        );

        $empty = Livewire::test(EditorHost::class, $this->host([]))->instance()->getPageBuilderDocumentArray();

        $this->assertSame([], $empty['blocks']);
    }

    public function test_the_editor_renders_the_switch_the_item_controls_and_the_counter(): void
    {
        $block = ['id' => self::G, 'type' => 'gallery', 'data' => ['slideshow' => false, 'items' => [
            ['id' => self::I1, 'media_id' => 1],
            ['id' => self::I2, 'media_id' => null, 'url' => '', 'alt' => '', 'caption' => '', 'credit' => ''],
        ]]];

        $html = Blade::render('<x-pagebuilder::editor :blocks="[$block]" :block-types="[]" :context="$context" />', [
            'block' => $block,
            'context' => [
                'options' => ['source' => 'media', 'picker' => true, 'upload' => 'deferred'],
                'previews' => [self::I1 => ['url' => '/m/1.jpg', 'alt' => 'Rathaus', 'caption' => 'Das Rathaus', 'credit' => 'Max Muster']],
                'galleryMax' => 30,
            ],
        ]);

        $this->assertStringContainsString('wire:model.live="pageBuilderBlocks.0.data.slideshow"', $html);
        $this->assertStringContainsString('Show as slideshow', $html);
        $this->assertStringContainsString('wire:key="pb-gallery-item-'.self::I1.'"', $html);
        $this->assertStringContainsString('Image 1 of 2', $html);
        $this->assertStringContainsString('Image 2 of 2', $html);
        $this->assertStringContainsString("movePageBuilderGalleryItem('".self::G."', '".self::I1."', 1)", $html);
        $this->assertStringContainsString("removePageBuilderGalleryItem('".self::G."', '".self::I2."')", $html);
        $this->assertStringContainsString('2 of 30 images', $html);
        $this->assertStringContainsString("addPageBuilderGalleryItem('".self::G."')", $html);
        $this->assertStringContainsString('pageBuilderUploads.'.self::I2, $html);
        $this->assertStringContainsString('blockId: &#039;'.self::I2.'&#039;', $html);
        $this->assertStringContainsString('wire:model.blur="pageBuilderBlocks.0.data.items.0.caption"', $html);
        $this->assertStringNotContainsString('Remove image</button>', $html);
    }

    public function test_the_add_button_is_disabled_at_the_maximum_and_an_empty_gallery_shows_a_hint(): void
    {
        $item = fn (string $id) => ['id' => $id, 'media_id' => 1];
        $full = ['id' => self::G, 'type' => 'gallery', 'data' => ['slideshow' => false, 'items' => [$item(self::I1), $item(self::I2)]]];
        $empty = ['id' => self::G, 'type' => 'gallery', 'data' => ['slideshow' => false, 'items' => []]];
        $context = ['options' => ['source' => 'media'], 'galleryMax' => 2];

        $htmlFull = Blade::render('<x-pagebuilder::editor :blocks="[$block]" :block-types="[]" :context="$context" />', ['block' => $full, 'context' => $context]);
        $htmlEmpty = Blade::render('<x-pagebuilder::editor :blocks="[$block]" :block-types="[]" :context="$context" />', ['block' => $empty, 'context' => $context]);

        $this->assertMatchesRegularExpression('/<button[^>]*addPageBuilderGalleryItem[^>]*disabled/', $htmlFull);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*addPageBuilderGalleryItem[^>]*disabled/', $htmlEmpty);
        $this->assertStringContainsString('This gallery has no images yet.', $htmlEmpty);
    }
}
