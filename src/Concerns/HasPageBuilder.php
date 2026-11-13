<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Concerns;

use Closure;
use Dandrum\PageBuilder\Blocks\GalleryBlock;
use Dandrum\PageBuilder\Blocks\QuoteBlock;
use Dandrum\PageBuilder\Contracts\ImageBearingBlock;
use Dandrum\PageBuilder\Contracts\ImageMediaResolver;
use Dandrum\PageBuilder\Support\BlockRegistry;
use Dandrum\PageBuilder\Support\PageBuilderDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LogicException;

/**
 * Turns any Livewire component into a block-based content editor for one
 * JSON-backed column of a bound model. The host component must:
 *
 * - call `$this->initializePageBuilder()` from its own `mount()`, after the
 *   bound model has been assigned to a property;
 * - implement `getPageBuilderModel()`, returning that bound model;
 * - for image blocks with uploads: override `storePageBuilderUpload()` (preferred) or
 *   `uploadPageBuilderImage()`, storing the file however the host app wants.
 *
 * Optional hooks: `getPageBuilderStorageField()`, `deletePageBuilderImage()`,
 * `pageBuilderImageOptions()`, `pageBuilderMediaIdRules()`, `pageBuilderUploadRules()`.
 *
 * On save the host validates with `pageBuilderRules()` / `pageBuilderMessages()` (merged into its
 * own rules), calls `storePendingPageBuilderUploads()` when images are uploaded in deferred mode,
 * and persists `getPageBuilderDocumentArray()` (already normalized and sanitized).
 */
trait HasPageBuilder
{
    use WithFileUploads;

    /** @var array<int, array{id: string, type: string, data: array<string, mixed>}> */
    public array $pageBuilderBlocks = [];

    /** @var array<string, TemporaryUploadedFile> keyed by block id */
    public array $pageBuilderUploads = [];

    public function initializePageBuilder(): void
    {
        $raw = data_get($this->getPageBuilderModel(), $this->getPageBuilderStorageField());

        $document = is_array($raw)
            ? PageBuilderDocument::fromArray($raw)
            : PageBuilderDocument::fromJson(is_string($raw) ? $raw : null);

        $registry = $this->pageBuilderRegistry();
        $blocks = [];

        foreach ($document->blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (isset($block['type']) && is_string($block['type']) && $registry->has($block['type'])) {
                $block['data'] = array_replace(
                    $registry->get($block['type'])->defaultData(),
                    is_array($block['data'] ?? null) ? $block['data'] : [],
                );
            }

            $blocks[] = $block;
        }

        $this->pageBuilderBlocks = $blocks;
        $this->pageBuilderUploads = [];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function pageBuilderBlockTypes(): array
    {
        return collect($this->pageBuilderRegistry()->all())
            ->map(fn ($block) => ['key' => $block::key(), 'label' => $block->label()])
            ->values()
            ->all();
    }

    public function addPageBuilderBlock(string $type): void
    {
        $lastBlock = collect($this->pageBuilderBlocks)->last();

        $this->insertPageBuilderBlock($type, $lastBlock['id'] ?? '');
    }

    /**
     * Inserts a new block of `$type` right after the block identified by
     * `$afterBlockId` — or at the very start of the list when `$afterBlockId`
     * is `''` (or no longer matches any block).
     */
    public function insertPageBuilderBlock(string $type, string $afterBlockId = ''): void
    {
        $blockType = $this->pageBuilderRegistry()->get($type);

        $newBlock = [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $blockType->defaultData(),
        ];

        $index = $afterBlockId === '' ? null : $this->findPageBuilderBlockIndex($afterBlockId);

        if ($index === null) {
            array_unshift($this->pageBuilderBlocks, $newBlock);
        } else {
            array_splice($this->pageBuilderBlocks, $index + 1, 0, [$newBlock]);
        }
    }

    public function movePageBuilderBlockUp(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null || $index === 0) {
            return;
        }

        $this->swapPageBuilderBlocks($index, $index - 1);
    }

    public function movePageBuilderBlockDown(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null || $index === array_key_last($this->pageBuilderBlocks)) {
            return;
        }

        $this->swapPageBuilderBlocks($index, $index + 1);
    }

    /**
     * Drag & drop target (Livewire 4 `wire:sort`): moves the block to `$position` (zero-based,
     * clamped to the list). An unknown id does nothing.
     */
    public function sortPageBuilderBlock(string $blockId, int $position): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null) {
            return;
        }

        $position = max(0, min($position, count($this->pageBuilderBlocks) - 1));

        if ($position === $index) {
            return;
        }

        $block = array_splice($this->pageBuilderBlocks, $index, 1);
        array_splice($this->pageBuilderBlocks, $position, 0, $block);
    }

    public function duplicatePageBuilderBlock(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null) {
            return;
        }

        $copy = $this->pageBuilderBlocks[$index];
        $copy['id'] = (string) Str::uuid();

        // Gallery items are upload slots keyed by their id: the copy gets its own ids (and no pending uploads).
        if (is_array($copy['data']['items'] ?? null)) {
            $copy['data']['items'] = array_map(
                fn ($item) => is_array($item) ? array_replace($item, ['id' => (string) Str::uuid()]) : $item,
                $copy['data']['items'],
            );
        }

        array_splice($this->pageBuilderBlocks, $index + 1, 0, [$copy]);
    }

    public function removePageBuilderBlock(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null) {
            return;
        }

        $data = $this->pageBuilderBlocks[$index]['data'] ?? [];
        $imageUrl = $data['url'] ?? $data['imageUrl'] ?? null;

        if (filled($imageUrl) && is_string($imageUrl) && $this->pageBuilderImageSource() === 'url') {
            $this->deletePageBuilderImage($imageUrl);
        }

        unset($this->pageBuilderUploads[$blockId]);

        foreach ($this->pageBuilderGalleryItems($index) as $item) {
            unset($this->pageBuilderUploads[(string) ($item['id'] ?? '')]);
        }

        unset($this->pageBuilderBlocks[$index]);
        $this->pageBuilderBlocks = array_values($this->pageBuilderBlocks);
    }

    /**
     * Not called by the shipped `<x-pagebuilder::editor>` (which reorders via `wire:sort` and
     * the move up/down buttons), but kept as a public API for a host that wires up its own
     * reordering.
     *
     * @param  array<int, string>  $orderedIds
     */
    public function reorderPageBuilderBlocks(array $orderedIds): void
    {
        $blocksById = collect($this->pageBuilderBlocks)->keyBy('id');

        $this->pageBuilderBlocks = collect($orderedIds)
            ->map(fn (string $id) => $blocksById->get($id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Livewire hook for `wire:model="pageBuilderUploads.{blockId}"`.
     */
    public function updatedPageBuilderUploads(mixed $value, string $key): void
    {
        if (! $value instanceof TemporaryUploadedFile) {
            return;
        }

        $slot = $this->pageBuilderImageSlot($key);
        $mode = $this->pageBuilderImageOptions()['upload'] ?? 'immediate';

        if ($slot === null || $mode === 'off') {
            unset($this->pageBuilderUploads[$key]);

            return;
        }

        try {
            $this->validateOnly(
                "pageBuilderUploads.{$key}",
                ["pageBuilderUploads.{$key}" => $this->pageBuilderUploadRules()],
                $this->pageBuilderMessages(),
            );
        } catch (ValidationException $e) {
            unset($this->pageBuilderUploads[$key]);

            throw $e;
        }

        if ($mode === 'deferred') {
            // Kept in $pageBuilderUploads until the host calls storePendingPageBuilderUploads().
            $this->setPageBuilderSlotData($slot, array_replace(
                $this->pageBuilderSlotData($slot),
                ['media_id' => null, $slot['urlKey'] => ''],
            ));

            return;
        }

        $this->uploadPageBuilderBlockImage($key, $value);

        unset($this->pageBuilderUploads[$key]);
    }

    /**
     * Immediate-mode upload of `$file` into the block `$blockId`.
     */
    public function uploadPageBuilderBlockImage(string $blockId, TemporaryUploadedFile $file): void
    {
        $slot = $this->pageBuilderImageSlot($blockId);

        if ($slot === null) {
            return;
        }

        $data = $this->pageBuilderSlotData($slot);
        $stored = $this->storePageBuilderUpload($blockId, $file, $data);

        $this->setPageBuilderSlotData($slot, array_replace($data, $stored));
    }

    /**
     * Host hook: store the uploaded file and return the data keys to merge into the image block
     * (`['media_id' => …]`, or the URL key: `url` for image and gallery items, `imageUrl` for image + text).
     * `$blockId` is the block's id, or the id of a gallery item; `$data` is the block's (or the item's)
     * current data (alt, caption, credit as typed by the user). The default delegates to
     * `uploadPageBuilderImage()`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function storePageBuilderUpload(string $blockId, TemporaryUploadedFile $file, array $data): array
    {
        $urlKey = $this->pageBuilderImageSlot($blockId)['urlKey'] ?? null;

        return [($urlKey ?? 'url') => $this->uploadPageBuilderImage($file)];
    }

    public function hasPendingPageBuilderUploads(): bool
    {
        return $this->pendingPageBuilderUploads() !== [];
    }

    /**
     * Stores every pending (deferred) upload through `storePageBuilderUpload()` and merges the
     * result into its block. Call after validation and authorization.
     */
    public function storePendingPageBuilderUploads(): void
    {
        foreach ($this->pendingPageBuilderUploads() as $blockId => $file) {
            $slot = $this->pageBuilderImageSlot((string) $blockId);

            if ($slot === null) {
                continue;
            }

            $data = $this->pageBuilderSlotData($slot);
            $stored = $this->storePageBuilderUpload((string) $blockId, $file, $data);

            // The image part is reset (alt/caption/credit went into the stored media); other keys
            // of the block (an image + text block's html, position, align) or the item's id stay.
            $this->setPageBuilderSlotData($slot, array_replace(
                $data,
                $this->emptyPageBuilderImageData($slot['urlKey']),
                $stored,
            ));
        }

        $this->pageBuilderUploads = [];
    }

    /**
     * An image block picked from the host's media library (event dispatched by the host's picker).
     */
    #[On('pagebuilder-image-picked')]
    public function pickPageBuilderImage(string $blockId, int|string $mediaId): void
    {
        $slot = $this->pageBuilderImageSlot($blockId);

        if ($slot === null) {
            return;
        }

        if ($this->pageBuilderImageSource() !== 'media') {
            return;
        }

        $validator = Validator::make(
            ['media_id' => $mediaId],
            ['media_id' => $this->pageBuilderMediaIdRules()],
        );

        if ($validator->fails()) {
            $this->addError($this->pageBuilderSlotPrefix($slot).'media_id', (string) $validator->errors()->first('media_id'));

            return;
        }

        $prefix = $this->pageBuilderSlotPrefix($slot);

        $this->resetErrorBag([
            $prefix.'media_id',
            $prefix.'alt',
            $prefix.'caption',
            $prefix.'credit',
            "pageBuilderUploads.{$blockId}",
        ]);

        unset($this->pageBuilderUploads[$blockId]);

        $this->setPageBuilderSlotData($slot, array_replace(
            $this->pageBuilderSlotData($slot),
            $this->emptyPageBuilderImageData($slot['urlKey']),
            ['media_id' => is_numeric($mediaId) ? (int) $mediaId : $mediaId],
        ));
    }

    /**
     * Removes the picked/uploaded/URL image from an image or image + text block or a gallery item, keeping the block (the slot) itself.
     */
    public function clearPageBuilderImage(string $blockId): void
    {
        $slot = $this->pageBuilderImageSlot($blockId);

        if ($slot === null) {
            return;
        }

        $this->setPageBuilderSlotData($slot, array_replace(
            $this->pageBuilderSlotData($slot),
            $this->emptyPageBuilderImageData($slot['urlKey']),
        ));

        unset($this->pageBuilderUploads[$blockId]);
    }

    /**
     * Appends an empty image slot to a gallery block. Does nothing once `gallery.max_images` is reached.
     */
    public function addPageBuilderGalleryItem(string $blockId): void
    {
        $index = $this->findPageBuilderGalleryIndex($blockId);

        if ($index === null || count($this->pageBuilderGalleryItems($index)) >= GalleryBlock::maxImages()) {
            return;
        }

        $this->pageBuilderBlocks[$index]['data']['items'] = [
            ...$this->pageBuilderGalleryItems($index),
            (new GalleryBlock)->emptyItem(),
        ];
    }

    public function removePageBuilderGalleryItem(string $blockId, string $itemId): void
    {
        $index = $this->findPageBuilderGalleryIndex($blockId);

        if ($index === null) {
            return;
        }

        $items = $this->pageBuilderGalleryItems($index);
        $position = $this->findPageBuilderGalleryItemIndex($items, $itemId);

        if ($position === null) {
            return;
        }

        unset($items[$position]);
        unset($this->pageBuilderUploads[$itemId]);
        $this->resetErrorBag();

        $this->pageBuilderBlocks[$index]['data']['items'] = array_values($items);
    }

    /**
     * Moves a gallery item by `$offset` (clamped to the list; normally -1 or 1).
     */
    public function movePageBuilderGalleryItem(string $blockId, string $itemId, int $offset): void
    {
        $index = $this->findPageBuilderGalleryIndex($blockId);

        if ($index === null) {
            return;
        }

        $items = $this->pageBuilderGalleryItems($index);
        $position = $this->findPageBuilderGalleryItemIndex($items, $itemId);

        if ($position === null) {
            return;
        }

        $target = max(0, min($position + $offset, count($items) - 1));

        if ($target === $position) {
            return;
        }

        $item = array_splice($items, $position, 1);
        array_splice($items, $target, 0, $item);

        $this->pageBuilderBlocks[$index]['data']['items'] = $items;
    }

    /**
     * Drops a pending (not yet stored) upload of one block.
     */
    public function clearPageBuilderUpload(string $blockId): void
    {
        unset($this->pageBuilderUploads[$blockId]);
        $this->resetErrorBag("pageBuilderUploads.{$blockId}");
    }

    /**
     * Image block options. Defaults to `config('pagebuilder.image')`; hosts override this per
     * component (e.g. to switch the upload off when the actor may not upload).
     *
     * @return array<string, mixed>
     */
    public function pageBuilderImageOptions(): array
    {
        return array_replace([
            'source' => 'url',
            'resolver' => null,
            'upload' => 'immediate',
            'picker' => false,
            'upload_required' => ['alt'],
            'render_view' => null,
            'notice' => null,
        ], (array) config('pagebuilder.image', []));
    }

    /**
     * Rules a `media_id` must pass (on pick and on save). Hosts extend this, e.g. with a
     * "belongs to this site" rule.
     *
     * @return array<int, mixed>
     */
    protected function pageBuilderMediaIdRules(): array
    {
        $resolver = $this->pageBuilderResolver();

        $rules = ['integer'];

        if ($resolver !== null) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($resolver): void {
                if (! array_key_exists($value, $resolver->findMany([$value]))) {
                    $fail(__('The image no longer exists.'));
                }
            };
        }

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    protected function pageBuilderUploadRules(): array
    {
        return ['file', 'image', 'max:10240'];
    }

    /**
     * Validation rules for the whole editor state, without validating — merge them into the host's
     * own rules and run a single `validate()`.
     *
     * @return array<string, mixed>
     */
    public function pageBuilderRules(): array
    {
        $registry = $this->pageBuilderRegistry();

        $rules = [
            'pageBuilderBlocks' => ['array', 'max:'.(int) config('pagebuilder.max_blocks', 200)],
            'pageBuilderBlocks.*.id' => ['required', 'string', 'uuid', 'distinct'],
            'pageBuilderBlocks.*.type' => ['required', Rule::in(array_keys($registry->all()))],
            'pageBuilderBlocks.*.data' => ['array'],
        ];

        foreach ($this->pageBuilderBlocks as $i => $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || ! $registry->has($block['type'])) {
                continue;
            }

            $blockType = $registry->get($block['type']);

            foreach ($blockType->rules() as $field => $fieldRules) {
                $rules["pageBuilderBlocks.{$i}.data.{$field}"] = $fieldRules;
            }

            if ($blockType instanceof GalleryBlock) {
                $rules["pageBuilderBlocks.{$i}.data.items.*.id"] = ['required', 'string', 'uuid', 'distinct'];

                foreach ($this->pageBuilderGalleryItems($i) as $j => $item) {
                    $rules = array_replace($rules, $this->pageBuilderImageRulesFor(
                        "pageBuilderBlocks.{$i}.data.items.{$j}.",
                        (string) ($item['id'] ?? ''),
                        'url',
                    ));
                }

                continue;
            }

            if (! $blockType instanceof ImageBearingBlock) {
                continue;
            }

            $rules = array_replace($rules, $this->pageBuilderImageRulesFor(
                "pageBuilderBlocks.{$i}.data.",
                (string) ($block['id'] ?? ''),
                $blockType->imageUrlKey(),
            ));
        }

        return $rules;
    }

    /**
     * Rules of one image slot (an image block, or a gallery item): a picked image, a pending upload
     * with its mandatory texts, or (source `url`) the URL plus alt text.
     *
     * @return array<string, mixed>
     */
    private function pageBuilderImageRulesFor(string $prefix, string $slotId, string $urlKey): array
    {
        $options = $this->pageBuilderImageOptions();
        $required = array_values(array_intersect(['alt', 'caption', 'credit'], (array) ($options['upload_required'] ?? [])));
        $maxima = ['alt' => 250, 'caption' => 1000, 'credit' => 255];
        $rules = [];

        if ($this->pageBuilderImageSource() === 'media') {
            if (isset($this->pageBuilderUploads[$slotId])) {
                $rules["pageBuilderUploads.{$slotId}"] = array_merge(['required'], $this->pageBuilderUploadRules());

                foreach ($required as $field) {
                    $rules[$prefix.$field] = ['required', 'string', 'max:'.$maxima[$field]];
                }
            } else {
                $rules[$prefix.'media_id'] = array_merge(['required'], $this->pageBuilderMediaIdRules());
            }

            return $rules;
        }

        $rules[$prefix.$urlKey] = ['required', 'string'];
        $rules[$prefix.'alt'] = ['required', 'string', 'max:255'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function pageBuilderMessages(): array
    {
        $quoteMessages = [];

        foreach ($this->pageBuilderBlocks as $i => $block) {
            if (is_array($block) && ($block['type'] ?? null) === QuoteBlock::key()) {
                $quoteMessages["pageBuilderBlocks.{$i}.data.text.max"] = __('A quote may be at most :max characters long.');
            }
        }

        return $quoteMessages + [
            'pageBuilderBlocks.*.data.items.max' => __('A gallery may contain at most :max images.', ['max' => GalleryBlock::maxImages()]),
            'pageBuilderBlocks.*.data.items.*.media_id.required' => __('Please choose or upload an image.'),
            'pageBuilderBlocks.*.data.items.*.url.required' => __('Please choose or upload an image.'),
            'pageBuilderBlocks.*.data.items.*.alt.required' => __('Alt text is missing.'),
            'pageBuilderBlocks.*.data.items.*.caption.required' => __('Caption is missing.'),
            'pageBuilderBlocks.*.data.items.*.credit.required' => __('Photographer or source is missing.'),
            'pageBuilderBlocks.max' => __('At most :max blocks are allowed.'),
            'pageBuilderBlocks.*.type.in' => __('This block type is not available.'),
            'pageBuilderBlocks.*.data.html.max' => __('A paragraph may be at most :max characters long.'),
            'pageBuilderBlocks.*.data.media_id.required' => __('Please choose or upload an image.'),
            'pageBuilderBlocks.*.data.url.required' => __('Please choose or upload an image.'),
            'pageBuilderBlocks.*.data.imageUrl.required' => __('Please choose or upload an image.'),
            'pageBuilderBlocks.*.data.alt.required' => __('Alt text is missing.'),
            'pageBuilderBlocks.*.data.caption.required' => __('Caption is missing.'),
            'pageBuilderBlocks.*.data.credit.required' => __('Photographer or source is missing.'),
            'pageBuilderUploads.*.uploaded' => __('The image could not be uploaded.'),
        ];
    }

    /**
     * Validates the editor state on its own. Prefer merging `pageBuilderRules()` into the host's
     * own `validate()` call; this is the convenience for editors that stand alone.
     */
    public function validatePageBuilderBlocks(): void
    {
        $rules = $this->pageBuilderRules();

        // Livewire's validate() throws MissingRulesException on *any* empty rules array,
        // so "nothing to check" must short-circuit instead.
        if ($rules === []) {
            return;
        }

        $this->validate($rules, $this->pageBuilderMessages());
    }

    /**
     * @return array{version: int, blocks: array<int, array<string, mixed>>}
     */
    public function getPageBuilderDocumentArray(): array
    {
        return (new PageBuilderDocument(1, $this->pageBuilderBlocks))->normalized()->toArray();
    }

    public function getPageBuilderDocumentJson(): string
    {
        return (new PageBuilderDocument(1, $this->pageBuilderBlocks))->normalized()->toJson();
    }

    /**
     * Everything `<x-pagebuilder::editor>` needs beyond the blocks themselves.
     *
     * @return array{options: array<string, mixed>, previews: array<string, array<string, string|null>|null>, uploads: array<string, array{previewUrl: string|null, name: string}>, alignment: bool, galleryMax: int}
     */
    public function pageBuilderEditorContext(): array
    {
        $options = $this->pageBuilderImageOptions();
        $previews = [];

        if (($options['source'] ?? 'url') === 'media' && ($resolver = $this->pageBuilderResolver()) !== null) {
            $ids = (new PageBuilderDocument(1, $this->pageBuilderBlocks))->mediaIds();
            $found = $ids === [] ? [] : $resolver->findMany($ids);

            // Image blocks are keyed by the block id, gallery items by the item id.
            $slots = [];

            foreach ($this->pageBuilderBlocks as $i => $block) {
                if ($this->isPageBuilderImageBlock($block)) {
                    $slots[(string) $block['id']] = $block['data']['media_id'] ?? null;
                }

                foreach ($this->pageBuilderGalleryItems($i) as $item) {
                    $slots[(string) ($item['id'] ?? '')] = $item['media_id'] ?? null;
                }
            }

            foreach ($slots as $slotId => $mediaId) {
                if ($mediaId === null || $mediaId === '') {
                    continue;
                }

                $media = $found[$mediaId] ?? null;

                $previews[$slotId] = $media === null
                    ? null
                    : ['url' => $resolver->previewUrl($media)] + $resolver->metadata($media);
            }
        }

        $uploads = [];

        foreach ($this->pageBuilderUploads as $blockId => $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            $uploads[(string) $blockId] = [
                'previewUrl' => $file->isPreviewable() ? $file->temporaryUrl() : null,
                'name' => $file->getClientOriginalName(),
            ];
        }

        return [
            'options' => $options,
            'previews' => $previews,
            'uploads' => $uploads,
            'alignment' => (bool) config('pagebuilder.alignment', true),
            'galleryMax' => GalleryBlock::maxImages(),
        ];
    }

    protected function getPageBuilderStorageField(): string
    {
        return 'content';
    }

    public function deletePageBuilderImage(string $url): void
    {
        // No-op by default — override to clean up the file in the host app's storage.
    }

    /**
     * Legacy single-purpose upload hook (source `url`). Override this or `storePageBuilderUpload()`.
     */
    public function uploadPageBuilderImage(TemporaryUploadedFile $file): string
    {
        throw new LogicException('Override uploadPageBuilderImage() or storePageBuilderUpload() in the component using HasPageBuilder.');
    }

    abstract protected function getPageBuilderModel(): Model;

    private function pageBuilderRegistry(): BlockRegistry
    {
        return app(BlockRegistry::class);
    }

    private function pageBuilderImageSource(): string
    {
        return ($this->pageBuilderImageOptions()['source'] ?? 'url') === 'media' ? 'media' : 'url';
    }

    private function pageBuilderResolver(): ?ImageMediaResolver
    {
        return app()->bound(ImageMediaResolver::class) ? app(ImageMediaResolver::class) : null;
    }

    /**
     * Pending uploads that still belong to an image block or a gallery item.
     *
     * @return array<string, TemporaryUploadedFile>
     */
    private function pendingPageBuilderUploads(): array
    {
        $pending = [];

        foreach ($this->pageBuilderUploads as $blockId => $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            if ($this->pageBuilderImageSlot((string) $blockId) !== null) {
                $pending[(string) $blockId] = $file;
            }
        }

        return $pending;
    }

    /**
     * The URL data key of the block at `$index` when it is an image-bearing block
     * (image, image + text), otherwise null.
     */
    private function pageBuilderImageUrlKey(int $index): ?string
    {
        $type = $this->pageBuilderBlocks[$index]['type'] ?? null;

        if (! is_string($type) || ! $this->pageBuilderRegistry()->has($type)) {
            return null;
        }

        $blockType = $this->pageBuilderRegistry()->get($type);

        return $blockType instanceof ImageBearingBlock ? $blockType->imageUrlKey() : null;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function isPageBuilderImageBlock(array $block): bool
    {
        $type = $block['type'] ?? null;

        return is_string($type)
            && $this->pageBuilderRegistry()->has($type)
            && $this->pageBuilderRegistry()->get($type) instanceof ImageBearingBlock;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPageBuilderImageData(string $urlKey): array
    {
        return ['media_id' => null, $urlKey => '', 'alt' => '', 'caption' => '', 'credit' => ''];
    }

    /**
     * An "image slot" is whatever holds one image: an image-bearing block (addressed by the block id)
     * or one gallery item (addressed by the item id). Returns where it lives, or null for an unknown id.
     *
     * @return array{block: int, item: int|null, urlKey: string}|null
     */
    private function pageBuilderImageSlot(string $slotId): ?array
    {
        $index = $this->findPageBuilderBlockIndex($slotId);

        if ($index !== null) {
            $urlKey = $this->pageBuilderImageUrlKey($index);

            return $urlKey === null ? null : ['block' => $index, 'item' => null, 'urlKey' => $urlKey];
        }

        foreach ($this->pageBuilderBlocks as $i => $block) {
            foreach ($this->pageBuilderGalleryItems($i) as $j => $item) {
                if (($item['id'] ?? null) === $slotId) {
                    return ['block' => $i, 'item' => $j, 'urlKey' => 'url'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array{block: int, item: int|null, urlKey: string}  $slot
     */
    private function pageBuilderSlotPrefix(array $slot): string
    {
        return "pageBuilderBlocks.{$slot['block']}.data.".($slot['item'] === null ? '' : "items.{$slot['item']}.");
    }

    /**
     * @param  array{block: int, item: int|null, urlKey: string}  $slot
     * @return array<string, mixed>
     */
    private function pageBuilderSlotData(array $slot): array
    {
        $data = $slot['item'] === null
            ? ($this->pageBuilderBlocks[$slot['block']]['data'] ?? [])
            : ($this->pageBuilderBlocks[$slot['block']]['data']['items'][$slot['item']] ?? []);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array{block: int, item: int|null, urlKey: string}  $slot
     * @param  array<string, mixed>  $data
     */
    private function setPageBuilderSlotData(array $slot, array $data): void
    {
        if ($slot['item'] === null) {
            $this->pageBuilderBlocks[$slot['block']]['data'] = $data;
        } else {
            $this->pageBuilderBlocks[$slot['block']]['data']['items'][$slot['item']] = $data;
        }
    }

    /**
     * The items of the gallery block at `$index` (empty for any other block).
     *
     * @return array<int, array<string, mixed>>
     */
    private function pageBuilderGalleryItems(int $index): array
    {
        $block = $this->pageBuilderBlocks[$index] ?? null;

        if (! is_array($block) || ($block['type'] ?? null) !== GalleryBlock::key() || ! is_array($block['data']['items'] ?? null)) {
            return [];
        }

        return array_filter($block['data']['items'], 'is_array');
    }

    private function findPageBuilderGalleryIndex(string $blockId): ?int
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        return $index !== null && ($this->pageBuilderBlocks[$index]['type'] ?? null) === GalleryBlock::key() ? $index : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function findPageBuilderGalleryItemIndex(array $items, string $itemId): ?int
    {
        foreach ($items as $position => $item) {
            if (($item['id'] ?? null) === $itemId) {
                return $position;
            }
        }

        return null;
    }

    private function findPageBuilderBlockIndex(string $blockId): ?int
    {
        foreach ($this->pageBuilderBlocks as $i => $block) {
            if (($block['id'] ?? null) === $blockId) {
                return $i;
            }
        }

        return null;
    }

    private function swapPageBuilderBlocks(int $a, int $b): void
    {
        [$this->pageBuilderBlocks[$a], $this->pageBuilderBlocks[$b]] =
            [$this->pageBuilderBlocks[$b], $this->pageBuilderBlocks[$a]];
    }
}
