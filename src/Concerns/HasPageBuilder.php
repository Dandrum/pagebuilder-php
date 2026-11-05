<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Concerns;

use Closure;
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

        $index = $this->findPageBuilderBlockIndex($key);
        $urlKey = $index === null ? null : $this->pageBuilderImageUrlKey($index);
        $mode = $this->pageBuilderImageOptions()['upload'] ?? 'immediate';

        if ($urlKey === null || $mode === 'off') {
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
            $this->pageBuilderBlocks[$index]['data']['media_id'] = null;
            $this->pageBuilderBlocks[$index]['data'][$urlKey] = '';

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
        $index = $this->findPageBuilderBlockIndex($blockId);

        if ($index === null) {
            return;
        }

        if ($this->pageBuilderImageUrlKey($index) === null) {
            return;
        }

        $stored = $this->storePageBuilderUpload($blockId, $file, $this->pageBuilderBlocks[$index]['data']);

        $this->pageBuilderBlocks[$index]['data'] = array_replace($this->pageBuilderBlocks[$index]['data'], $stored);
    }

    /**
     * Host hook: store the uploaded file and return the data keys to merge into the image block
     * (`['media_id' => …]`, or the URL key: `url` for image, `imageUrl` for image + text). `$data` is
     * the block's current data (alt, caption, credit as typed by the user). The default delegates to
     * `uploadPageBuilderImage()`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function storePageBuilderUpload(string $blockId, TemporaryUploadedFile $file, array $data): array
    {
        $index = $this->findPageBuilderBlockIndex($blockId);
        $urlKey = $index === null ? null : $this->pageBuilderImageUrlKey($index);

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
            $index = $this->findPageBuilderBlockIndex((string) $blockId);

            if ($index === null) {
                continue;
            }

            $stored = $this->storePageBuilderUpload(
                (string) $blockId,
                $file,
                $this->pageBuilderBlocks[$index]['data'] ?? [],
            );

            // The image part is reset (alt/caption/credit went into the stored media); other keys
            // of the block (an image + text block's html, position, align) stay.
            $this->pageBuilderBlocks[$index]['data'] = array_replace(
                $this->pageBuilderBlocks[$index]['data'] ?? [],
                $this->emptyPageBuilderImageData($this->pageBuilderImageUrlKey($index) ?? 'url'),
                $stored,
            );
        }

        $this->pageBuilderUploads = [];
    }

    /**
     * An image block picked from the host's media library (event dispatched by the host's picker).
     */
    #[On('pagebuilder-image-picked')]
    public function pickPageBuilderImage(string $blockId, int|string $mediaId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);

        $urlKey = $index === null ? null : $this->pageBuilderImageUrlKey($index);

        if ($index === null || $urlKey === null) {
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
            $this->addError("pageBuilderBlocks.{$index}.data.media_id", (string) $validator->errors()->first('media_id'));

            return;
        }

        $this->resetErrorBag([
            "pageBuilderBlocks.{$index}.data.media_id",
            "pageBuilderBlocks.{$index}.data.alt",
            "pageBuilderBlocks.{$index}.data.caption",
            "pageBuilderBlocks.{$index}.data.credit",
            "pageBuilderUploads.{$blockId}",
        ]);

        unset($this->pageBuilderUploads[$blockId]);

        $this->pageBuilderBlocks[$index]['data'] = array_replace(
            $this->pageBuilderBlocks[$index]['data'],
            $this->emptyPageBuilderImageData($urlKey),
            ['media_id' => is_numeric($mediaId) ? (int) $mediaId : $mediaId],
        );
    }

    /**
     * Removes the picked/uploaded/URL image from an image or image + text block, keeping the block itself.
     */
    public function clearPageBuilderImage(string $blockId): void
    {
        $index = $this->findPageBuilderBlockIndex($blockId);
        $urlKey = $index === null ? null : $this->pageBuilderImageUrlKey($index);

        if ($index === null || $urlKey === null) {
            return;
        }

        $this->pageBuilderBlocks[$index]['data'] = array_replace(
            $this->pageBuilderBlocks[$index]['data'],
            $this->emptyPageBuilderImageData($urlKey),
        );

        unset($this->pageBuilderUploads[$blockId]);
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
        $options = $this->pageBuilderImageOptions();
        $source = $this->pageBuilderImageSource();
        $required = array_values(array_intersect(['alt', 'caption', 'credit'], (array) ($options['upload_required'] ?? [])));
        $maxima = ['alt' => 250, 'caption' => 1000, 'credit' => 255];

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

            if (! $blockType instanceof ImageBearingBlock) {
                continue;
            }

            $id = (string) ($block['id'] ?? '');
            $prefix = "pageBuilderBlocks.{$i}.data.";

            if ($source === 'media') {
                if (isset($this->pageBuilderUploads[$id])) {
                    $rules["pageBuilderUploads.{$id}"] = array_merge(['required'], $this->pageBuilderUploadRules());

                    foreach ($required as $field) {
                        $rules[$prefix.$field] = ['required', 'string', 'max:'.$maxima[$field]];
                    }
                } else {
                    $rules[$prefix.'media_id'] = array_merge(['required'], $this->pageBuilderMediaIdRules());
                }

                continue;
            }

            $rules[$prefix.$blockType->imageUrlKey()] = ['required', 'string'];
            $rules[$prefix.'alt'] = ['required', 'string', 'max:255'];
        }

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
     * @return array{options: array<string, mixed>, previews: array<string, array<string, string|null>|null>, uploads: array<string, array{previewUrl: string|null, name: string}>, alignment: bool}
     */
    public function pageBuilderEditorContext(): array
    {
        $options = $this->pageBuilderImageOptions();
        $previews = [];

        if (($options['source'] ?? 'url') === 'media' && ($resolver = $this->pageBuilderResolver()) !== null) {
            $ids = (new PageBuilderDocument(1, $this->pageBuilderBlocks))->mediaIds();
            $found = $ids === [] ? [] : $resolver->findMany($ids);

            foreach ($this->pageBuilderBlocks as $block) {
                $mediaId = $block['data']['media_id'] ?? null;

                if ($mediaId === null || $mediaId === '' || ! $this->isPageBuilderImageBlock($block)) {
                    continue;
                }

                $media = $found[$mediaId] ?? null;

                $previews[$block['id']] = $media === null
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
     * Pending uploads that still belong to an image block.
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

            $index = $this->findPageBuilderBlockIndex((string) $blockId);

            if ($index !== null && $this->pageBuilderImageUrlKey($index) !== null) {
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
