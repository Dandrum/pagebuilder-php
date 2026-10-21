<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Providers;

use Dandrum\PageBuilder\Support\BlockRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class PageBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/pagebuilder.php', 'pagebuilder');

        $this->app->singleton(BlockRegistry::class, function () {
            $registry = new BlockRegistry;

            foreach (config('pagebuilder.blocks', []) as $blockClass) {
                $registry->register($blockClass);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        // Load default translations
        $this->loadJsonTranslationsFrom(__DIR__.'/../../resources/lang');

        // Override if published
        $this->loadJsonTranslationsFrom($this->app->langPath('vendor/pagebuilder'));

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'pagebuilder');

        Blade::componentNamespace('Dandrum\\PageBuilder\\View\\Components', 'pagebuilder');

        $this->consoleCommands();
    }

    private function consoleCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../resources/lang' => $this->app->langPath('vendor/pagebuilder'),
        ], 'pagebuilder-translations');

        $this->publishes([
            __DIR__.'/../../config/pagebuilder.php' => config_path('pagebuilder.php'),
        ], 'pagebuilder-config');

        $this->publishes([
            __DIR__.'/../../resources/views' => $this->app->resourcePath('views/vendor/pagebuilder'),
        ], 'pagebuilder-views');
    }
}
