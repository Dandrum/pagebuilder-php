<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests;

use Dandrum\PageBuilder\Providers\PageBuilderServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            PageBuilderServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('pagebuilder.sanitizer.cache_path', false);
    }
}
