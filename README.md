# livewire-pagebuilder

Laravel Livewire block-based content builder. Stores a block list (`paragraph`, `heading`,
`image`, `image_text`) as a single JSON document on any model column, so it can be plugged into
any Livewire component without forcing a base class.

Requires Laravel 11-13 and Livewire 3-4 (PHP 8.2+). Drag & drop needs Livewire 4 (`wire:sort`);
on Livewire 3 the grip handle does nothing and the move up/down buttons remain.

## Install

As a VCS repository (not published on Packagist), same as `dandrum/datatable`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/Dandrum/pagebuilder-php.git" }
    ],
    "require": {
        "dandrum/livewire-pagebuilder": "dev-main"
    }
}
```

Frontend requirements of the host app:

- **Trix** (`npm i trix`, `import 'trix'` in your JS bundle, `trix/dist/trix.css` in your CSS).
  Self-host it; do not load it from a CDN.
- **Alpine.js** - already bundled with Livewire 3/4, nothing to install.
- The package CSS (below).

## Usage

Add `HasPageBuilder` to any Livewire component, implement `getPageBuilderModel()`, and call
`initializePageBuilder()` from `mount()`:

```php
use Dandrum\PageBuilder\Concerns\HasPageBuilder;

class ArticleForm extends Component
{
    use HasPageBuilder;

    public Article $article;

    public function mount(Article $article): void
    {
        $this->article = $article;
        $this->initializePageBuilder();
    }

    protected function getPageBuilderModel(): Article
    {
        return $this->article;
    }

    public function save(): void
    {
        $this->validate($this->pageBuilderRules(), $this->pageBuilderMessages());

        $this->article->content = $this->getPageBuilderDocumentArray(); // normalized + sanitized
        $this->article->save();
    }
}
```

```blade
<x-pagebuilder::editor
    :blocks="$pageBuilderBlocks"
    :block-types="$this->pageBuilderBlockTypes()"
    :context="$this->pageBuilderEditorContext()"
/>
```

Render the stored JSON anywhere (works outside Livewire):

```blade
<div class="pb-content">
    <x-pagebuilder::render :blocks="$article->content" />
</div>
```

`getPageBuilderStorageField()` defaults to `'content'`. The stored value may be a JSON string or an
array (a model cast).

## Styling

The package ships two plain CSS files (no Tailwind, no build step of its own). Import them from the
host's stylesheet:

```css
@import 'trix/dist/trix.css';
@import '../../vendor/dandrum/livewire-pagebuilder/resources/css/pagebuilder-editor.css'; /* editor UI */
@import '../../vendor/dandrum/livewire-pagebuilder/resources/css/pagebuilder-content.css'; /* rendered output */
```

Everything is driven by custom properties with neutral defaults. Override them after the import by
redeclaring them on `.pagebuilder` (editor) or `.pb-content` (output):

| Selector | Variables |
|---|---|
| `.pagebuilder` | `--pb-color-text`, `--pb-color-muted`, `--pb-color-border`, `--pb-color-surface`, `--pb-color-surface-muted`, `--pb-color-accent`, `--pb-color-on-accent`, `--pb-color-danger`, `--pb-focus-ring`, `--pb-radius`, `--pb-space-1` ... `--pb-space-6`, `--pb-font` |
| `.pb-content` | `--pb-content-font`, `--pb-content-color`, `--pb-content-measure`, `--pb-content-gap`, `--pb-link`, `--pb-caption-color`, `--pb-content-radius` |

`php artisan vendor:publish --tag=pagebuilder-assets` copies the CSS files to
`resources/css/vendor/pagebuilder` if you prefer a copy over importing from `vendor/`.

## Config (`config/pagebuilder.php`)

| Key | Default | Meaning |
|---|---|---|
| `blocks` | all four | Registered block type classes |
| `max_blocks` | `200` | Validation cap for the number of blocks |
| `max_html_length` | `20000` | Validation cap for one paragraph's HTML |
| `alignment` | `true` | `false`: no align selects, the renderer ignores `align` |
| `sanitizer.allowed` | `p,div,br,strong,em,a[href],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote` | HTMLPurifier whitelist (headings and quotes come from the Trix toolbar) |
| `sanitizer.schemes` / `nofollow` / `cache_path` | `http,https,mailto` / `true` / `null` | URI schemes, `rel=nofollow`, definition cache (`false` = none) |
| `image.source` | `url` | `url` stores `data.url` (`data.imageUrl` for image + text); `media` stores `data.media_id`. All `image.*` options apply to both image-bearing blocks |
| `image.resolver` | `null` | `ImageMediaResolver` class, required for `media` |
| `image.upload` | `immediate` | `immediate`, `deferred` (stored on save) or `off` |
| `image.picker` | `false` | Show "Choose from library" |
| `image.upload_required` | `['alt']` | Subset of `alt`, `caption`, `credit` required with a new upload |
| `image.render_view` | `null` | View used by `<x-pagebuilder::render>` for image blocks and the image side of image + text blocks (receives `data`, `block`, `media`) |
| `image.notice` | `null` | Text shown when neither picker nor upload is available |

Override `pageBuilderImageOptions()` on the component to change these per component.

## Media library integration (`image.source = media`)

1. Implement `Contracts\ImageMediaResolver` (`findMany`, `previewUrl`, `metadata`) and set
   `image.resolver`.
2. With `image.picker = true` the block's button dispatches the browser/Livewire event
   `pagebuilder-pick-image` with `{ blockId }`. The host shows its own picker and answers with
   `$this->dispatch('pagebuilder-image-picked', blockId: $id, mediaId: $mediaId)`. The trait ignores
   ids it does not own (several editors per page) and validates `mediaId` against
   `pageBuilderMediaIdRules()` - override that protected method to add rules such as "belongs to
   this site". A client can dispatch the event itself, so the same rules run again on save.
3. Uploads: with `upload = deferred` the file stays in `$pageBuilderUploads[$blockId]` until the
   host calls `storePendingPageBuilderUploads()` (after validation and authorization). That calls
   `storePageBuilderUpload($blockId, $file, $data)`, which returns the keys to merge into the block
   (`['media_id' => 12]`). `pageBuilderUploadRules()` sets the file rules. With `immediate` the same
   hook runs right after the upload is validated. `uploadPageBuilderImage()` is no longer abstract:
   the default `storePageBuilderUpload()` delegates to it for `url`-source hosts.
4. `pageBuilderRules()` / `pageBuilderMessages()` return rules without validating, so a host merges
   them into its own `validate()` call.

## Rich text (Trix) toolbar

Paragraph and image + text blocks share one Trix toolbar: bold, italic, headings H1-H6, link, bullet and
numbered list, quote. Trix only knows `heading1` natively, so `components/rich-text.blade.php` registers
`heading2`..`heading6` on `Trix.config.blockAttributes` from an Alpine `x-init` before the HTML is loaded
(no JS file, no build step in the package - the host only loads Trix). Headings and quotes are stored as
`<h1>`-`<h6>` and `<blockquote>`; keep them in `sanitizer.allowed` if you override that list, and the
rendered `.pb-prose` styles them (`--pb-heading-font`, `--pb-quote-border`, `--pb-quote-color`).
The standalone `heading` block is still shipped but not needed any more - leave it out of `blocks`.

## Image + text block

`image_text` is an image-bearing block (`Contracts\ImageBearingBlock`, like `image`) plus a rich text and
`imagePosition` (`left`|`right`, switchable in the editor). It uses the same `image.*` options, picker and
upload events (`pagebuilder-pick-image` / `pagebuilder-image-picked` carry the block id for both types), the
same deferred upload and `upload_required` metadata, and its `media_id` is part of
`PageBuilderDocument::mediaIds()`. Its text counts in `plainText()`. The render view puts the image beside
the text (stacked below 40rem); `image.render_view` renders the image side.

## Sanitizing

Paragraph / image+text HTML is sanitized with HTMLPurifier (`ezyang/htmlpurifier`, a hard
dependency) whenever a document is normalized: `PageBuilderDocument::normalized()`, which
`getPageBuilderDocumentArray()` / `getPageBuilderDocumentJson()` apply. Normalization also drops
unknown, malformed and duplicate-id blocks, empty paragraphs, and unknown data keys; it is
idempotent. Rebind `Contracts\HtmlSanitizer` to use a different sanitizer. The render views output
the stored HTML as-is, so always store normalized documents.

## JSON schema

```json
{
    "version": 1,
    "blocks": [
        { "id": "uuid", "type": "heading", "data": { "level": 2, "text": "...", "align": "left" } },
        { "id": "uuid", "type": "paragraph", "data": { "html": "<p>...</p>", "align": "left" } },
        { "id": "uuid", "type": "image", "data": { "media_id": 12, "caption": "optional override" } },
        { "id": "uuid", "type": "image", "data": { "url": "...", "alt": "...", "caption": "..." } },
        { "id": "uuid", "type": "image_text", "data": { "imageUrl": "...", "alt": "...", "imagePosition": "left|right", "html": "...", "align": "left" } },
        { "id": "uuid", "type": "image_text", "data": { "media_id": 12, "caption": "optional override", "imagePosition": "right", "html": "..." } }
    ]
}
```

## Extending with new block types

Implement `Dandrum\PageBuilder\Contracts\BlockType` (and optionally `ContentAwareBlock` to
normalize/sanitize your data and contribute to the plain text), then publish the config
(`php artisan vendor:publish --tag=pagebuilder-config`) and append the class to `blocks`.

## Upgrade notes

- Package views no longer use Tailwind utility classes: import the shipped CSS (above).
- `uploadPageBuilderImage()` is no longer abstract (it throws unless overridden).
- Image blocks have new data keys `media_id` and `credit`; existing documents keep working.
- Paragraph `trix-change` now stores `trix.value` and syncs by block id instead of `@entangle`.
- `ezyang/htmlpurifier` is now required.

## Tests

```sh
composer install
vendor/bin/phpunit
```

See `.claude/rules/` for architecture, Laravel conventions, and testing notes.
