# Implementation guide: integrating `dandrum/livewire-pagebuilder` into a Laravel project

Audience: **Claude Code (or a developer) working in a host Laravel app** that wants a block editor for one or more
content columns. After installation this file lives at
`vendor/dandrum/livewire-pagebuilder/docs/IMPLEMENTATION_GUIDE.md`. Work through section 1 (checklist) in order; the other
sections are the detail behind each step. A complete real-world host exists as reference (section 14).

---

## 0. What the package is - and is not

| It is | It is not |
|---|---|
| A Livewire **trait** (`HasPageBuilder`) that turns any Livewire component into an editor for **one JSON column** of a bound Eloquent model | A base class, a model, a migration, a route, a controller or a database table |
| Two Blade entry points: `<x-pagebuilder::editor>` (admin UI) and `<x-pagebuilder::render>` (read-only output, works outside Livewire) | A media library. Images come from a host-provided `ImageMediaResolver` (library mode) or are plain URLs (url mode) |
| Six block types: `paragraph`, `heading`, `image`, `image_text`, `quote`, `gallery` (extendable) | A JS/CSS build. It ships **plain CSS** and no JS; the host loads Trix |
| HTML sanitizing (HTMLPurifier) + document normalization | Autosave, drafts, undo/redo, versioned schema migrations (none implemented) |

Consequences for the host:

- **No package migrations.** The host adds one nullable text/JSON column per editable model (section 4).
- **No routes/controllers.** The editor runs inside the host's own Livewire component(s).
- The host owns: persistence, authorization, media storage, the picker UI, public styling of the output.

Requirements: PHP 8.2+, Laravel 11-13, Livewire 3-4 (drag & drop sorting needs Livewire 4's `wire:sort`; on Livewire 3 the
grip does nothing and the move up/down buttons remain), `ezyang/htmlpurifier` (installed automatically), `ext-gd` only for
image uploads in tests.

---

## 1. Integration checklist (do in this order)

1. [ ] **Install** the package (section 2) and run `composer update dandrum/livewire-pagebuilder` + `php artisan package:discover`.
2. [ ] **Frontend**: `npm i trix`, import Trix in the JS bundle, import Trix + package CSS in the stylesheet (section 3).
3. [ ] **Database**: add a nullable `longText`/`json` column to each model that holds a document, cast it to `array`
       (section 4).
4. [ ] **Publish + edit the config** `config/pagebuilder.php`: block list, image source (`url` or `media`), upload mode, render
       hooks (section 5). Decide *library vs. URL mode first*; it changes everything below.
5. [ ] **Host Livewire component(s)**: `use HasPageBuilder`, `getPageBuilderModel()`, `initializePageBuilder()` in `mount()`,
       merge `pageBuilderRules()` into validation, persist `getPageBuilderDocumentArray()` (section 6).
6. [ ] **Editor view**: `<x-pagebuilder::editor ...>` inside the component's Blade view (section 6.3).
7. [ ] **Image handling** - choose one: URL mode (section 7) or media-library mode (section 8: resolver, picker, upload hook,
       usage tracking).
8. [ ] **Rendering**: `<x-pagebuilder::render>` inside `<div class="pb-content">` in the public view; optional render hooks
       for image and gallery (section 9).
9. [ ] **Everything else that writes content** (seeders, factories, importers, APIs) must go through
       `PageBuilderDocument::fromArray(...)->normalized()` (section 10).
10. [ ] **Translations**: the package strings are English literals in JSON files; decide whether to override (section 11).
11. [ ] **Tests** for the host integration (section 12).
12. [ ] **Docs for the host**: paste the snippet from section 15 into the host's `CLAUDE.md` / `.claude/rules/`.

Decisions to ask the user **before** coding if not stated: (a) which model(s)/column(s) get an editor, (b) image source
(`url` or media library - if library: which model, how are images authorized/scoped, who may upload), (c) which block types
(default recommendation: all except `heading`), (d) where the output is rendered and how it should look, (e) CSP/Alpine
constraints of the host (section 3.4).

---

## 2. Installation

The package is **not on Packagist**; it is a VCS repository (same as `dandrum/datatable`). In the host `composer.json`:

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

- `minimum-stability` of the host may need to allow `dev` for this package (use the `dev-main` constraint as shown, or set
  `"minimum-stability": "dev", "prefer-stable": true`).
- To test an unmerged package branch use `"dev-<branch-name>"`. Switch back to `dev-main` (or a tag) once merged.
- A private repository needs a GitHub token in `auth.json` (gitignored); containers building the image need it too.
- The service provider is auto-discovered (`extra.laravel.providers`). After `composer update` run
  `php artisan package:discover` (any composer script run does it). In Docker projects run everything via the dev container.
- When changing the package locally, `composer update dandrum/livewire-pagebuilder` pulls the new commit.

What the provider registers: `BlockRegistry` and `HtmlSanitizer` singletons, `ImageMediaResolver` (only if
`pagebuilder.image.resolver` is set), the `pagebuilder::` view and Blade component namespaces, JSON translations. Publish tags:
`pagebuilder-config`, `pagebuilder-views`, `pagebuilder-assets` (CSS copy), `pagebuilder-translations`.

```sh
php artisan vendor:publish --tag=pagebuilder-config      # config/pagebuilder.php  (do this)
php artisan vendor:publish --tag=pagebuilder-assets      # optional: copies the CSS to resources/css/vendor/pagebuilder
php artisan vendor:publish --tag=pagebuilder-views       # only if you must change editor markup (avoid: you own upgrades then)
php artisan vendor:publish --tag=pagebuilder-translations
```

---

## 3. Frontend

### 3.1 Trix (required, self-hosted)

```sh
npm i trix
```
```js
// resources/js/app.js (the bundle that is loaded on the admin pages containing the editor)
import 'trix'
```
```css
/* admin stylesheet */
@import 'trix/dist/trix.css';
```

- Do **not** load Trix from a CDN (DSGVO: no third-party requests; see the host's `datenschutz` rule).
- The package registers the extra heading levels (`heading2`..`heading6`) itself from an Alpine `x-init` in
  `components/rich-text.blade.php`; the host needs no Trix configuration. File attachments are blocked inside Trix.

### 3.2 Alpine / Livewire

Alpine comes with Livewire (3 and 4); nothing to install. The editor page must load Livewire's scripts normally
(`@livewireScripts` or Livewire's automatic asset injection). Drag & drop uses Livewire 4's `wire:sort` (SortableJS is bundled).

### 3.3 CSS (plain files, `pb-*` classes, `--pb-*` custom properties)

Editor and output styles are two separate files; import the one(s) each stylesheet needs:

```css
/* admin.css (editor) */
@import 'trix/dist/trix.css';
@import '../../vendor/dandrum/livewire-pagebuilder/resources/css/pagebuilder-editor.css';

/* public.css (rendered output) */
@import '../../vendor/dandrum/livewire-pagebuilder/resources/css/pagebuilder-content.css';
```

(Adjust the relative path to `vendor/`.) The package views carry **no Tailwind classes**, so the host's Tailwind `@source`
needs no entry for the package. Theme by redeclaring variables **after** the import:

```css
.pagebuilder { --pb-color-accent: #0b5fff; --pb-color-on-accent: #fff; --pb-radius: 6px; /* ... */ }
.pb-content  { --pb-content-font: Georgia, serif; --pb-content-measure: 70ch; --pb-link: #0b5fff; }
```

Variables: `.pagebuilder` -> `--pb-color-text|muted|border|surface|surface-muted|accent|on-accent|danger`, `--pb-focus-ring`,
`--pb-radius`, `--pb-space-1..6`, `--pb-font`, `--pb-editor-min-height`; `.pb-content` -> `--pb-content-font|color|measure|gap`,
`--pb-link`, `--pb-caption-color`, `--pb-content-radius`, quote block `--pb-quote-block-font|color|accent|size|margin`,
inline quote `--pb-quote-border|color`, `--pb-heading-font`, `--pb-gallery-gap`. Keep host overrides in **one** file
(e.g. `resources/css/<project>/pagebuilder.css`) imported after the package CSS. Never copy package markup to restyle it.
Inputs inside the editor should use the host's input colours via these variables (e.g. white inputs on a cream page).

After editing CSS the host must rebuild assets (`npm run build` / the dev server).

### 3.4 CSP / Alpine CSP build - important limitation

The editor views use **inline Alpine expressions** (`x-data="{...}"`, `x-on:...`). They do **not** work with the CSP build of
Alpine (`@alpinejs/csp`) or a strict CSP without `'unsafe-eval'`. Use the editor only on pages with Livewire's regular Alpine
(typical: the admin area). The **render** component outputs static HTML and has no JS dependency, so public pages can keep a
strict CSP. If the host has a strict CSP in the admin, ask the user before integrating.

---

## 4. Database

The package needs **no table of its own**. Per editable model add one nullable column.

```php
// migration (additive, with a working down())
Schema::table('articles', function (Blueprint $table) {
    $table->longText('content')->nullable();   // or ->json('content')->nullable()
});
```

- Stored shape: `{"version":1,"blocks":[{"id":"<uuid>","type":"paragraph","data":{...}}]}`; `null` = no content.
- `longText` + `array` cast is the proven choice (works on sqlite tests, Postgres, MySQL). `json`/`jsonb` also works; the trait
  accepts either a JSON string or an already-cast array (`initializePageBuilder()` handles both).
- Typical size: a long article is well below 1 MB; the block cap (`max_blocks` 200) and `max_html_length` (20 000 per paragraph)
  bound it.
- Column name: default is **`content`**. Any other name: override `getPageBuilderStorageField()` in the component (it is
  `protected` and returns the attribute name). One component = one editor = one column. For two documents on one model use two
  components (or a component per tab), each with its own storage field.
- Several models (articles, pages, ...) can use the editor; each just needs its own column and a component using the trait.

Model:

```php
protected function casts(): array
{
    return ['content' => 'array'];
}

public function contentDocument(): \Dandrum\PageBuilder\Support\PageBuilderDocument
{
    return \Dandrum\PageBuilder\Support\PageBuilderDocument::fromArray($this->content);
}

public function contentPlainText(): string            // reading time, search, meta descriptions, excerpts
{
    return $this->contentDocument()->plainText();
}

/** @return list<int> ids of library images used in the text (blocks + gallery items) */
public function contentMediaIds(): array
{
    return array_values(array_unique(array_map('intval', $this->contentDocument()->mediaIds())));
}
```

**Migrating existing plain-text/HTML content** into blocks (one-off data migration):

```php
foreach (Article::query()->whereNotNull('body')->cursor() as $a) {
    $html = '<div>'.nl2br(e($a->body)).'</div>';   // or the existing HTML
    $a->content = (new PageBuilderDocument(1, [[
        'id' => (string) Str::uuid(), 'type' => 'paragraph', 'data' => ['html' => $html, 'align' => 'left'],
    ]]))->normalized()->toArray();
    $a->saveQuietly();
}
```
Always pass migrated data through `normalized()` (sanitizing + schema cleanup). Paragraphs may contain `<p>` (migrated) as well as `<div>` (Trix).

**Media usage table (library mode only):** if the host tracks where images are used (e.g. a polymorphic `mediables` pivot so a
used image cannot be deleted), sync it on save from `contentMediaIds()` (section 8.6). The package does not do that.

---

## 5. Configuration (`config/pagebuilder.php`)

Publish it and keep **only what you change** meaningful; the package merges defaults (`mergeConfigFrom`).

| Key | Default | Notes |
|---|---|---|
| `blocks` | all six | Order = order in the insert menu. **Recommendation: remove `HeadingBlock`** - headings (H1-H6), lists and quotes already come from the paragraph's Trix toolbar. Append own `BlockType` classes here. |
| `max_blocks` | 200 | validation cap |
| `max_html_length` | 20000 | per paragraph / image + text |
| `max_quote_length` | 500 | quote block validation + storage truncation |
| `alignment` | true | left/center/right buttons; false -> no buttons, renderer ignores `align` |
| `sanitizer.allowed` | `p,div,br,strong,em,a[href],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote` | HTMLPurifier `HTML.Allowed`. Trix emits `div`, `h1`-`h6`, `blockquote`; keep them if you override |
| `sanitizer.schemes` / `nofollow` / `cache_path` | `http,https,mailto` / true / null | `cache_path` null = `storage_path('framework/cache/pagebuilder-purifier')` (must be writable in the container, www-data); `false` = no cache |
| `image.source` | `url` | `url` -> `data.url` (`data.imageUrl` for image + text); `media` -> `data.media_id` resolved through `image.resolver` |
| `image.resolver` | null | class implementing `ImageMediaResolver`; **required** for `media` |
| `image.upload` | `immediate` | `immediate` (stored on selection), `deferred` (kept until the host calls `storePendingPageBuilderUploads()` after validation + authorization - **use this in library mode**), `off` |
| `image.picker` | false | show "Choose from library"; dispatches `pagebuilder-pick-image` |
| `image.upload_required` | `['alt']` | subset of `alt`,`caption`,`credit` required together with a new upload |
| `image.render_view` | null | host Blade view for image blocks and the image side of image + text (gets `data`, `block`, `media`) |
| `image.notice` | null | text when neither picker nor upload is available |
| `gallery.max_images` | 30 | cap (validation, normalization, add button) |
| `gallery.render_view` | null | host view instead of the plain list (gets `images`, `slideshow`, `block`, `data`) |

`pageBuilderImageOptions()` (public method on the component, defaults to `config('pagebuilder.image')`) can be overridden **per
request** - e.g. switch the upload `off` when the user may not upload, or `picker` off while no site/tenant is chosen
(section 8.4). Image alt text, caption, credit are mandatory for uploads per `upload_required`; picked library images take
their alt/credit live from the library.

---

## 6. The host Livewire component

### 6.1 Minimal editor (URL mode, one model)

```php
use Dandrum\PageBuilder\Concerns\HasPageBuilder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class EditArticle extends Component
{
    use HasPageBuilder;            // brings WithFileUploads along

    public Article $article;

    public function mount(Article $article): void
    {
        $this->authorize('update', $article);
        $this->article = $article;
        $this->initializePageBuilder();          // AFTER the model property is assigned
    }

    protected function getPageBuilderModel(): Model   // abstract in the trait, signature must match
    {
        return $this->article;
    }

    public function save(): void
    {
        $this->authorize('update', $this->article);

        $this->validate(
            $this->pageBuilderRules() + [/* the host's own rules */],
            $this->pageBuilderMessages() + [/* the host's own messages */],
        );

        $this->storePendingPageBuilderUploads();   // no-op unless upload = deferred

        $this->article->content = $this->getPageBuilderDocumentArray();   // normalized + sanitized
        $this->article->save();
    }

    public function render()
    {
        return view('livewire.edit-article');
    }
}
```

Rules that cannot be broken:

1. `initializePageBuilder()` must run in the host's `mount()` **after** the bound model is assigned (the trait has no mount
   hook of its own).
2. `getPageBuilderModel()` is `abstract protected function getPageBuilderModel(): Model`.
3. Reserved public properties of the trait: `pageBuilderBlocks`, `pageBuilderUploads`, `pageBuilderGalleryUploads`. Do not
   redeclare them. Method prefix `pageBuilder`/`PageBuilder` is reserved for the trait.
4. Merge rules with `+`/`array_merge`, **never call `validate()` twice** (Livewire throws `MissingRulesException` on an empty
   rules array - `validatePageBuilderBlocks()` handles that for stand-alone editors only).
5. Persist `getPageBuilderDocumentArray()` (array, for an `array` cast) or `getPageBuilderDocumentJson()` (string) - both
   normalize + sanitize. Never persist `$pageBuilderBlocks` directly: the renderer prints stored HTML as-is.
6. One editor per Livewire component. A page may hold several components (each with its own `pageBuilderBlocks`); the picker
   event handling ignores block ids it does not own.

### 6.2 Recommended save order (library mode / when authorization matters)

```
1. normalize/prepare the host's own form
2. $rules = $this->pageBuilderRules() + hostRules;   $validated = $this->validate($rules, $messages)
3. authorize (policies). If $this->hasPendingPageBuilderUploads(): authorize the *upload* right too (a client can tamper)
4. persist host side effects that must precede (e.g. a featured image)
5. $this->storePendingPageBuilderUploads();      // calls storePageBuilderUpload() per pending file, merges media_id into blocks
6. save the model with 'content' => $this->getPageBuilderDocumentArray()
7. sync media usage links from the saved content (section 8.6)
```
Wrap 4-7 in a DB transaction if the host stores files as rows.

### 6.3 Editor view

```blade
<x-pagebuilder::editor
    :blocks="$pageBuilderBlocks"
    :block-types="$this->pageBuilderBlockTypes()"
    :context="$this->pageBuilderEditorContext()"
/>
@error('pageBuilderBlocks') <span class="error">{{ $message }}</span> @enderror
```

- Must be rendered **inside** the Livewire component's own view (it is a plain Blade component using `wire:` attributes of the
  host component, not a nested Livewire component).
- `pageBuilderEditorContext()` returns options, previews (one `findMany` for the whole document), pending upload previews,
  alignment flag and `galleryMax`. It is recomputed each render; keep the resolver's `findMany()` to one query.
- Per-field errors render inside the blocks (`@error` on `pageBuilderBlocks.{i}.data.*`); an error on a media id shows on the
  image field.
- In library mode add the picker component next to it (section 8.3).

### 6.4 Overriding trait methods

Trait methods are overridable. In the **component class** simply declare the method. If the override lives in **another
trait** that you `use` together with `HasPageBuilder`, resolve the collision explicitly:

```php
use HasPageBuilder, UsesMyMediaLibrary {
    UsesMyMediaLibrary::pageBuilderImageOptions insteadof HasPageBuilder;
    UsesMyMediaLibrary::pageBuilderMediaIdRules insteadof HasPageBuilder;
    UsesMyMediaLibrary::pageBuilderUploadRules insteadof HasPageBuilder;
    UsesMyMediaLibrary::storePageBuilderUpload insteadof HasPageBuilder;
}
use WithFileUploads;   // using it again next to HasPageBuilder is fine
```
This is the pattern of the reference host: one reusable `UsesMediathekBlockEditor` trait shared by the article and the page form.

### 6.5 Public API of the trait (reference)

Hooks the host may override:

| Method | Visibility | Purpose |
|---|---|---|
| `getPageBuilderModel(): Model` | protected, **abstract** | the bound model |
| `getPageBuilderStorageField(): string` | protected | column/attribute, default `'content'` |
| `pageBuilderImageOptions(): array` | public | per-request image options (see section 5) |
| `pageBuilderMediaIdRules(): array` | protected | rules a `media_id` must pass on pick **and** on save (add "belongs to this site/tenant") |
| `pageBuilderUploadRules(): array` | protected | file rules, default `['file','image','max:10240']` |
| `storePageBuilderUpload(string $blockId, TemporaryUploadedFile $file, array $data): array` | public | **store the file, return keys to merge into the block** (`['media_id' => 12]`, or `['url' => '...']` / `['imageUrl' => '...']` for url mode); `$data` carries alt/caption/credit typed by the user; `$blockId` may be a gallery **item** id |
| `uploadPageBuilderImage(TemporaryUploadedFile $file): string` | public | legacy url-mode hook; default `storePageBuilderUpload()` delegates here; throws `LogicException` unless overridden |
| `deletePageBuilderImage(string $url): void` | public | url mode: called when a block with an image URL is removed; no-op default - delete the stored file here |

Called by the host:

| Method | When |
|---|---|
| `initializePageBuilder()` | `mount()` |
| `pageBuilderRules()` / `pageBuilderMessages()` | build validation without validating (merge them) |
| `validatePageBuilderBlocks()` | stand-alone editors only |
| `hasPendingPageBuilderUploads()` | before authorizing the upload right on save |
| `storePendingPageBuilderUploads()` | after validation + authorization (deferred mode) |
| `getPageBuilderDocumentArray()` / `getPageBuilderDocumentJson()` | persist |
| `pageBuilderBlockTypes()` / `pageBuilderEditorContext()` | editor view props |

Called by the editor via `wire:click`/`wire:model` (do not call from host code unless building a custom UI):
`insertPageBuilderBlock($type, $afterBlockId)`, `addPageBuilderBlock($type)`, `duplicatePageBuilderBlock`, `removePageBuilderBlock`,
`movePageBuilderBlockUp/Down`, `sortPageBuilderBlock($id, $position)` (drag & drop), `reorderPageBuilderBlocks(array $ids)`,
`clearPageBuilderImage`, `clearPageBuilderUpload`, `pickPageBuilderImage` (listens to `pagebuilder-image-picked`),
`addPageBuilderGalleryItem`, `removePageBuilderGalleryItem`, `movePageBuilderGalleryItem`, `applyPageBuilderGalleryField`,
`updatedPageBuilderUploads`, `updatedPageBuilderGalleryUploads`.

---

## 7. Image handling, mode A: plain URLs (`image.source = url`)

Simplest setup, no media library:

```php
// config/pagebuilder.php
'image' => ['source' => 'url', 'upload' => 'immediate', 'picker' => false, 'upload_required' => ['alt']],
```
```php
// component
public function uploadPageBuilderImage(TemporaryUploadedFile $file): string
{
    $path = $file->store('content-images', 'public');   // honour the project's disk config; never hard-code S3 vs local
    return Storage::disk('public')->url($path);
}

public function deletePageBuilderImage(string $url): void
{
    // map $url back to the path and delete it, only if nothing else uses it
}
```
Stored data: `{"url":"https://...","alt":"...","caption":"...","credit":"..."}` (`imageUrl` for image + text). `url` must be an
http(s) URL or a same-origin relative path; anything else is dropped on normalization. Alt text is required (max 255). Note
the URL (not an id) is stored, so moving files later means a data migration - prefer library mode for anything serious.

Without upload and picker set `upload => 'off'` and give `notice` text, or let users type URLs (the field accepts a URL).

---

## 8. Image handling, mode B: media library (`image.source = media`)

Use this when the host has an image model (variants, alt text, photographer, access rules). Blocks then store only
`media_id`; alt/credit/caption come **live** from the library, `caption` on the block is an optional per-usage override.

### 8.1 Resolver (required)

```php
use Dandrum\PageBuilder\Contracts\ImageMediaResolver;

class MediaResolver implements ImageMediaResolver
{
    /** @param list<int|string> $ids  @return array<int|string, Media> keyed by id; missing ids simply absent */
    public function findMany(array $ids): array
    {
        return $ids === [] ? [] : Media::query()->whereKey($ids)->with('variants')->get()->keyBy('id')->all();   // ONE query
    }

    public function previewUrl(mixed $media): ?string      // thumbnail for the editor; null while still processing
    {
        return $media instanceof Media ? $media->thumbnailUrl() : null;
    }

    /** @return array{alt: string, caption: string, credit: string} */
    public function metadata(mixed $media): array
    {
        return $media instanceof Media
            ? ['alt' => $media->alt_text, 'caption' => $media->caption, 'credit' => $media->photographer]
            : ['alt' => '', 'caption' => '', 'credit' => ''];
    }
}
```
Register via config: `'image' => ['source' => 'media', 'resolver' => MediaResolver::class, ...]` (bound as singleton by the provider).
`findMany` is called with all ids of a document in one go by both the editor context and `<x-pagebuilder::render>`.

### 8.2 Config

```php
'image' => [
    'source' => 'media',
    'resolver' => App\Support\MediaResolver::class,
    'upload' => 'deferred',                       // store only after validation + authorization
    'picker' => true,
    'upload_required' => ['alt', 'caption', 'credit'],
    'render_view' => 'frontend.pagebuilder.image',
],
'gallery' => ['max_images' => 30, 'render_view' => 'frontend.pagebuilder.gallery'],
```

### 8.3 Picker contract (events)

The package ships **no picker UI**. Contract:

1. With `image.picker = true` the "Choose from library" button dispatches the browser/Livewire event
   `pagebuilder-pick-image` with payload `{ blockId }`. `blockId` is the block id - or the **gallery item id**.
2. The host component (typically a child Livewire component with a modal) listens (`#[On('pagebuilder-pick-image')]`, method
   receives `string $blockId`), shows the library and answers:
   ```php
   $this->dispatch('pagebuilder-image-picked', blockId: $this->blockId, mediaId: $mediaId);
   ```
3. The trait's `pickPageBuilderImage()` handles `pagebuilder-image-picked`: ignores unknown block ids, validates `mediaId`
   against `pageBuilderMediaIdRules()`, sets `media_id` and clears alt/caption/credit and any pending upload.
4. The picker must **authorize itself** (it is a public Livewire endpoint): check the actor may view the library of the
   current site/tenant, re-check the chosen id against the authorized query in `pick()`, never write anything itself.

Picker UI tips (reference: `Admin\Media\Picker`): search + filters, "load more" instead of pagination, an empty state, a modal
that does not depend on Flux JS if the admin layout does not load it.

### 8.4 Per-request options and authorization

```php
public function pageBuilderImageOptions(): array
{
    $site = $this->currentSite();                    // whatever scopes the library in this project
    $mayUpload = $site !== null && Gate::allows('upload', [Media::class, $site]);

    return array_replace((array) config('pagebuilder.image', []), [
        'picker' => $site !== null,
        'upload' => $mayUpload ? 'deferred' : 'off',
        'notice' => $site === null ? __('Please choose a site first.') : null,
    ]);
}

protected function pageBuilderMediaIdRules(): array     // runs on pick AND on save (a client can dispatch the event itself)
{
    return ['integer', function (string $attribute, mixed $value, Closure $fail) {
        $media = is_numeric($value) ? Media::find((int) $value) : null;
        if ($media === null) { $fail(__('This image no longer exists.')); }
        elseif ($media->site_id !== $this->currentSite()?->id) { $fail(__('This image belongs to another site.')); }
    }];
}

protected function pageBuilderUploadRules(): array { return ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']; }

public function storePageBuilderUpload(string $blockId, TemporaryUploadedFile $file, array $data): array
{
    $media = app(UploadMedia::class)->handle($this->currentSite(), $file, [
        'alt_text' => trim((string) ($data['alt'] ?? '')),
        'caption' => trim((string) ($data['caption'] ?? '')),
        'photographer' => trim((string) ($data['credit'] ?? '')),
    ], auth()->user());

    return ['media_id' => $media->id];
}
```
Without a chosen tenant/site the option `picker/upload` should be off with a `notice`.

### 8.5 Upload flow (deferred - the recommended mode)

1. User picks a file -> `updatedPageBuilderUploads()` validates it with `pageBuilderUploadRules()`; invalid files are dropped.
   In `deferred` mode the file stays in `$pageBuilderUploads[$blockId]` and the block's `media_id`/url are emptied.
2. On save `pageBuilderRules()` adds: `pageBuilderUploads.{id}` required + the `upload_required` texts for that slot.
3. After validation **and authorization** the host calls `storePendingPageBuilderUploads()`: it calls
   `storePageBuilderUpload()` for every pending file and merges the returned keys (`media_id`) into the block; alt/caption/credit
   typed by the user are reset on the block (they now live in the stored media).
4. `immediate` mode stores right after the upload is validated (no authorization step in between: only for trusted editors).
5. A gallery has a multi-file input (`pageBuilderGalleryUploads.{blockId}`): every file becomes one item and takes the same path.

### 8.6 Usage tracking and deletion protection (host's job)

After saving, sync image usage from the document so that images still in use cannot be deleted:

```php
$ids = array_values(array_unique(array_map('intval', $model->contentMediaIds())));   // blocks + gallery items
$model->syncMedia($ids);          // host method: pivot sync, only images of the same site
```
Refuse to delete a library image that is still linked. Removing the featured image must not drop the link while the text
still uses it - sync the **union** of all sources.

### 8.7 Moves between sites/tenants

If images are scoped to a site and the model can move, re-run `pageBuilderMediaIdRules()` on save (it does, via
`pageBuilderRules()`); show the resulting per-block error and make the user replace the images.

---

## 9. Rendering

```blade
<div class="pb-content">
    <x-pagebuilder::render :blocks="$article->content" />
</div>
```

- `:blocks` accepts the full document array, a bare blocks array, a JSON string or `null`. Works in any Blade view, no Livewire
  needed.
- Unregistered block types are skipped (so removing a type from config never breaks old documents).
- All `media_id`s are resolved with **one** `findMany()`.
- **Stored HTML is printed raw** - that is why only normalized documents may be stored.
- Wrap in `.pb-content` and import `pagebuilder-content.css`; theme with `--pb-content-*` variables.
- Headings: Trix allows `<h1>`. If the page title is already the only `<h1>`, transform the blocks before rendering
  (e.g. map `<h1>` to `<h2>` in a `contentForDisplay()` accessor); the stored content stays untouched.
- Plain text for excerpts, reading time, search: `PageBuilderDocument::fromArray($content)->plainText()`.

### 9.1 Render hooks

`image.render_view` (image blocks and the image side of image + text) receives `$data` (block data), `$block`, `$media` (the
resolved host object or null):

```blade
{{-- resources/views/frontend/pagebuilder/image.blade.php --}}
@if ($media)
    <x-my.figure :media="$media" :caption="$data['caption'] ?? null" class="pb-block pb-block--image" />
@endif
```

`gallery.render_view` receives `$images` (resolved list: `id`, `media`, `url`, `alt`, `caption`, `credit`; missing media are
skipped), `$slideshow` (bool), `$block`, `$data`. Lightbox/slideshow behaviour is the host's job (the package ships none). If the
public pages use the Alpine **CSP** build, implement it as an `Alpine.data()` component, not inline `x-data`.

Without hooks the package prints plain figures (`<ul class="pb-gallery">`) which need no JS.

### 9.2 Document helpers (`Dandrum\PageBuilder\Support\PageBuilderDocument`)

`fromJson(?string)`, `fromArray(?array)`, `new PageBuilderDocument(1, $blocks)`, `->normalized()`, `->toArray()`, `->toJson()`,
`->mediaIds()` (distinct block + gallery item ids in document order), `->plainText()`, public readonly `$version`, `$blocks`.

---

## 10. Sanitizing and normalization (security-relevant)

- `PageBuilderDocument::normalized()` drops malformed, unregistered and duplicate-id blocks, whitelists data keys per block,
  sanitizes HTML with HTMLPurifier (`sanitizer.*` config), drops empty paragraphs/quotes/galleries, truncates quotes. It is
  idempotent. `getPageBuilderDocumentArray()/Json()` already apply it.
- **Every other write path** (seeders, factories, console importers, an API) must do
  `PageBuilderDocument::fromArray($data)->normalized()->toArray()` before storing - otherwise raw HTML reaches the renderer (XSS).
  Reference host: `SaveArticle` normalizes again and stores `null` when no block is left.
- Rebind `Contracts\HtmlSanitizer` in a service provider to use another sanitizer (must be idempotent).
- HTMLPurifier needs a writable cache directory (default `storage/framework/cache/pagebuilder-purifier`); in read-only
  containers set `sanitizer.cache_path` to a writable path or `false`.
- Image URLs (url mode) are restricted to http(s) or same-origin relative paths.

---

## 11. Translations

- The package uses `__('English sentence')` keys with JSON files `resources/lang/en.json` and `de.json` (loaded via
  `loadJsonTranslationsFrom`). Locale `de` works out of the box.
- Override or add languages: `php artisan vendor:publish --tag=pagebuilder-translations` -> `lang/vendor/pagebuilder/<locale>.json`.
- **Validation messages** returned by `pageBuilderMessages()` are English via `__()`. A host with another UI language should
  merge its own messages **after** (`$this->pageBuilderMessages()` first, host keys win) - see the long German
  `blockEditorMessages()` in the reference host - or translate through the published JSON.
- Host strings stay in the host's own translation files.

---

## 12. Testing the integration (host side)

Test the **host component**, not the package. Use `Livewire::test()` and the `RefreshDatabase` convention of the host.

```php
Livewire::actingAs($user)->test(EditArticle::class, ['article' => $article])
    ->call('insertPageBuilderBlock', 'paragraph')          // add a block
    ->set('pageBuilderBlocks.0.data.html', '<div>Hello <script>x</script></div>')
    ->call('save')
    ->assertHasNoErrors();

expect($article->fresh()->content['blocks'][0]['data']['html'])->not->toContain('<script>');   // sanitized
```

Cases worth a test per integration:
- save writes a normalized document; `null` for an empty editor; unknown/duplicate blocks are dropped;
- validation errors (`assertHasErrors('pageBuilderBlocks.0.data.alt')`...) and that nothing is saved on error;
- library mode: a picked id of a foreign site/tenant is rejected on pick **and** on save; a user without upload right gets
  `upload = off`; a tampered pending upload without the right is refused (403) and nothing is stored;
- deferred upload: `UploadedFile::fake()->image('a.jpg')` assigned to `pageBuilderUploads.<blockId>`, then `save` stores one
  media row and sets `media_id` (needs ext-gd; mark `#[RequiresPhpExtension('gd')]`);
- picker: `->dispatch('pagebuilder-image-picked', blockId: $id, mediaId: $media->id)`;
- usage sync after save; deletion of a used image refused;
- render: `$this->blade('<x-pagebuilder::render :blocks="$c" />', ['c' => $document])`. Blade tests rendering `@error` outside a
  Livewire render need `view()->share('errors', new \Illuminate\Support\ViewErrorBag)`;
- an in-memory `ImageMediaResolver` fake that counts `findMany()` calls proves the "one query per document" rule.
Block ids must be UUIDs in test data (`(string) Str::uuid()`), otherwise `pageBuilderRules()` rejects them.

When working as an agent: run **only the test you wrote**, not the whole suite (host project rule).

---

## 13. Extending: custom block types

```php
final class CalloutBlock implements BlockType, ContentAwareBlock
{
    public static function key(): string { return 'callout'; }
    public function label(): string { return __('Callout'); }
    public function defaultData(): array { return ['text' => '']; }
    public function rules(): array { return ['text' => ['nullable', 'string', 'max:300']]; }   // keys relative to data.
    public function editorView(): string { return 'blocks.edit.callout'; }        // host view; gets $block, $index
    public function renderView(): string { return 'blocks.render.callout'; }      // gets $data, $block; no Livewire bindings
    public function normalize(array $data): ?array { /* whitelist + clean; null drops the block */ }
    public function plainText(array $data): string { return (string) ($data['text'] ?? ''); }
}
```
- Append the class to `config('pagebuilder.blocks')`. The edit view binds `wire:model="pageBuilderBlocks.{{ $index }}.data.text"`
  (`wire:model.blur` for plain inputs). Render views must work **outside** Livewire.
- Views of package blocks are `pagebuilder::...`; host views use the host's namespace.
- Image-bearing custom block: also implement `Contracts\ImageBearingBlock` (`imageUrlKey()`) to get picker/upload/clear/validation
  for free (data shape `media_id` or url key + `alt`,`caption`,`credit`).
- Add `rules()` for everything the editor can set, `normalize()` for everything stored, and a test for both.

---

## 14. Reference host implementation (Newspaper / "Meine Lokalpost")

Where to copy from (paths relative to `E:\webProjekte\Newspaper\portal`, a Laravel 13 + Livewire 4 app):

| Concern | File |
|---|---|
| Config (library mode, deferred uploads, hooks) | `config/pagebuilder.php` |
| Resolver | `app/Support/MediathekImageResolver.php` |
| Reusable library wiring trait (options, media id rules, upload store, `insteadof` pattern) | `app/Livewire/Admin/Concerns/UsesMediathekBlockEditor.php` |
| Host component trait with save order | `app/Livewire/Admin/Articles/Concerns/HandlesArticleForm.php` (`persist()`) |
| Picker (modal, events, self-authorizing) | `app/Livewire/Admin/Media/Picker.php` + its view |
| Editor in the form | `resources/views/livewire/admin/articles/_form.blade.php` |
| Model column, cast, helpers | `app/Models/Article.php` (`content`, `contentDocument()`, `contentMediaIds()`, `contentForDisplay()`) |
| Save + normalize + usage sync | `app/Actions/SaveArticle.php` |
| Render + hooks | `resources/views/frontend/articles/show.blade.php`, `resources/views/frontend/pagebuilder/{image,gallery}.blade.php` |
| CSS theming | `resources/css/admin.css`, `public.css`, `resources/css/lokalpost/pagebuilder.css` |
| Tests | `tests/Feature/Admin/Articles/BlockEditorTest.php`, `tests/Feature/Admin/Media/PickerTest.php`, `tests/Feature/Blocks/RenderContentTest.php` |

---

## 15. Snippet for the host's `CLAUDE.md` / `.claude/rules/pagebuilder.md`

```markdown
## Block editor (dandrum/livewire-pagebuilder)

Content text is a page-builder document in `<table>.<column>` (`{"version":1,"blocks":[{id,type,data}]}`, null = empty).
Full guide: `vendor/dandrum/livewire-pagebuilder/docs/IMPLEMENTATION_GUIDE.md` - read it before touching the editor, its
config or any code that writes the column.
- Editor: Livewire component with `HasPageBuilder`; `initializePageBuilder()` in `mount()` after the model is set; merge
  `pageBuilderRules()` into the one `validate()`; persist only `getPageBuilderDocumentArray()` (normalized + sanitized).
- Anything else that writes the column (seeders, factories, imports) must run `PageBuilderDocument::fromArray($d)->normalized()`.
- Render with `<x-pagebuilder::render :blocks="$model->content" />` inside `.pb-content`; stored HTML is printed raw.
- Images: <url mode | library mode via App\...\Resolver; deferred uploads; picker events pagebuilder-pick-image / -picked>.
- CSS overrides only through `--pb-*` variables in <path>; never copy package markup.
- The editor needs regular Alpine (not the CSP build) and self-hosted Trix.
```

---

## 16. Common problems

| Symptom | Cause / fix |
|---|---|
| `Call to undefined ... getPageBuilderModel` / abstract method error | the component must implement `protected function getPageBuilderModel(): Model` |
| Editor empty although data exists | `initializePageBuilder()` called before the model property was assigned, or wrong `getPageBuilderStorageField()` |
| `MissingRulesException` | `validate()` called with an empty rules array; merge `pageBuilderRules()` into the host rules |
| Validation fails with "id must be a valid UUID" | block ids in seeded/test data are not UUIDs |
| Trait method collision error | override lives in another trait: add `insteadof` (section 6.4) |
| Blocks vanish on save | unregistered type, duplicate id or empty content - check `normalized()`; empty paragraphs are dropped by design |
| Pasted `<script>`/styles remain in output | content was written without `normalized()` (seeder/import) |
| Drag & drop does nothing | Livewire 3 (needs 4); use move up/down buttons |
| Trix toolbar missing / editor is a plain box | `import 'trix'` and `trix/dist/trix.css` not in the admin bundle; assets not rebuilt |
| Editor unstyled | package CSS not imported, or imported after host overrides |
| Picker button does nothing | `image.picker` false, or no component listens to `pagebuilder-pick-image` |
| Picked image rejected | `pageBuilderMediaIdRules()` fails (id missing or foreign site/tenant) |
| Upload ignored | `image.upload = off` (also set by an override of `pageBuilderImageOptions()`), file fails `pageBuilderUploadRules()`, or deferred upload never stored because `storePendingPageBuilderUploads()` was not called |
| "Placeholder" instead of an image in the editor | resolver `previewUrl()` returns null (variants still processing) |
| Editor breaks with CSP errors | Alpine CSP build / strict CSP in the admin (section 3.4) |
| `HTMLPurifier` cache write error | `storage/framework/cache/pagebuilder-purifier` not writable; set `sanitizer.cache_path` |
| N+1 queries in the editor/render | resolver `findMany()` must fetch all ids in one query and eager-load variants |

---

## 17. Changes that affect upgrades

- Package views carry no Tailwind utility classes: import the shipped CSS.
- `uploadPageBuilderImage()` is no longer abstract; prefer `storePageBuilderUpload()`.
- Image blocks have `media_id` and `credit` keys; older documents keep working.
- Paragraph editing syncs by block id (no `@entangle`); use `trix.value`.
- `ezyang/htmlpurifier` is required.
- Blocks added over time: `image_text`, `quote`, `gallery` (items are image **slots**: the id passed as `blockId` to the picker,
  upload hooks and `$pageBuilderUploads` is the **item** id). Hosts that handle upload/pick hooks must not assume the id belongs
  to a top-level block.
- Before changing the JSON schema, public trait signatures or the config shape in the package, check all consuming hosts
  (`dandrum/DotaLan`, Newspaper).
