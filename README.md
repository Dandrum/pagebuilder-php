# livewire-pagebuilder

Laravel Livewire block-based content builder. Stores a block list (`paragraph`, `heading`,
`image`, `image_text`) as a single JSON document on any model column, so it can be plugged into
any Livewire component without forcing a base class.

This package is in active development and not recommended for production use yet.

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

```sh
composer require dandrum/livewire-pagebuilder:dev-main
```

The host app must already load **Alpine.js** and **Trix** (`trix` + `trix/dist/trix.css`) in its
own asset bundle — the package ships no JS/CSS assets of its own.

## Usage

Add `HasPageBuilder` to any Livewire component, implement the two required methods, and call
`initializePageBuilder()` from `mount()`:

```php
use Dandrum\PageBuilder\Concerns\HasPageBuilder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

    public function uploadPageBuilderImage(TemporaryUploadedFile $file): string
    {
        return app(UploadService::class)->uploadImage($file, 'articles');
    }

    public function deletePageBuilderImage(string $url): void
    {
        app(UploadService::class)->deleteFile($url);
    }

    public function save(): void
    {
        $this->validatePageBuilderBlocks();

        $this->article->content = $this->getPageBuilderDocumentJson();
        $this->article->save();
    }
}
```

```blade
<x-pagebuilder::editor :blocks="$pageBuilderBlocks" :block-types="$this->pageBuilderBlockTypes()" />
```

Render the stored JSON anywhere (Blade views of this app, not just the Next.js frontend):

```blade
<x-pagebuilder::render :blocks="$article->content" />
```

`getPageBuilderStorageField()` defaults to `'content'` — override it if the bound model stores
the JSON under a different column.

## JSON schema

```json
{
    "version": 1,
    "blocks": [
        { "id": "uuid", "type": "heading", "data": { "level": 2, "text": "…", "align": "left" } },
        { "id": "uuid", "type": "paragraph", "data": { "html": "<p>…</p>", "align": "left" } },
        { "id": "uuid", "type": "image", "data": { "url": "…", "alt": "…", "caption": "…" } },
        { "id": "uuid", "type": "image_text", "data": { "imageUrl": "…", "imagePosition": "left", "html": "…", "align": "left" } }
    ]
}
```

## Extending with new block types

Implement `Dandrum\PageBuilder\Contracts\BlockType`, then publish `config/pagebuilder.php`
(`php artisan vendor:publish --tag=pagebuilder-config`) and append the new class to the `blocks`
array — no fork required.

See `.claude/rules/` for architecture, Laravel conventions, and testing notes.
