# Testing

## Current state: no test suite

There is no `phpunit.xml`, no `tests/` directory, and no test dependency in `composer.json` yet
(mirroring the sibling `dandrum/datatable` package, which also ships without one). Don't assume a
missing test run means something broke — there is nothing configured to run. The only available
verification today is:

```sh
# Syntax-check every PHP file
for f in $(find src config -name "*.php"); do php -l "$f"; done

# Style/static check
vendor/bin/pint --test
```

## If a test suite is added later

This package has no host Laravel application (no `app/`, no routes, no database) — a future test
suite needs a Laravel application context to boot the service provider, resolve views/components,
and mount Livewire components against. The standard approach for exactly this situation is
[`orchestra/testbench`](https://github.com/orchestral/testbench) as a `require-dev` dependency,
with a `TestCase` that registers `Dandrum\PageBuilder\Providers\PageBuilderServiceProvider` (and
`Livewire\LivewireServiceProvider`) in `getPackageProviders()`. Don't reach for a full Laravel
skeleton app checked into this repo just to run tests — that's what Testbench exists to avoid.

Things worth covering once a suite exists, roughly in priority order:
- `PageBuilderDocument::fromJson()`/`fromArray()` round-tripping, including `null`/empty input.
- `BlockRegistry` registering/resolving/rejecting an unknown key, and rejecting a class that
  doesn't implement `BlockType`.
- A minimal host Livewire component (test double, using `HasPageBuilder`) exercising
  add/duplicate/remove/reorder and the `pageBuilderUploads.{id}` upload hook end to end via
  Livewire's testing helpers (`Livewire::test(...)`).
- `validatePageBuilderBlocks()` actually failing/passing per block type's `rules()`.
- Each shipped block type's `editorView()`/`renderView()` rendering without a bound Livewire
  component for the render side (a package consumer may call `<x-pagebuilder::render>` outside any
  Livewire context, e.g. from a public-facing Blade page — this must not implicitly depend on
  being inside a Livewire render pass).
