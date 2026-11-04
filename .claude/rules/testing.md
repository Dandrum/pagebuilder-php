# Testing

PHPUnit 11/12 + [Orchestra Testbench](https://github.com/orchestral/testbench), no host app and no
database.

```sh
composer install
vendor/bin/phpunit           # or: composer test
vendor/bin/pint --test       # style
```

- `tests/TestCase.php` registers `LivewireServiceProvider` + `PageBuilderServiceProvider` and sets
  `pagebuilder.sanitizer.cache_path = false` (no purifier cache on disk).
- `tests/Fixtures/EditorHost.php` is a Livewire component using `HasPageBuilder` over an unsaved
  in-memory `Fixtures\Page` model; image options come from `config('pagebuilder.image')`, which a test
  sets before `Livewire::test(EditorHost::class, ['initial' => [...]])`. Its `storePageBuilderUpload()`
  records the file name and returns `['media_id' => 2]`. `Fixtures\ArrayMediaResolver` is an in-memory
  `ImageMediaResolver` that counts `findMany()` calls.
- `Unit/PurifierHtmlSanitizerTest`, `Unit/PageBuilderDocumentTest`, `Feature/HasPageBuilderTest`
  (sort, pick, upload modes, rules) and `Feature/RenderTest` (render outside Livewire, editor markup).
- Blade tests that render views using `@error` outside a Livewire render need
  `view()->share('errors', new ViewErrorBag)`.
- File uploads in tests use `UploadedFile::fake()->image(...)` (needs ext-gd).
