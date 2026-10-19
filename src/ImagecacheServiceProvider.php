<?php

namespace Ritterg\Imagecache;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ritterg\Imagecache\Console\ClearCommand;
use Ritterg\Imagecache\Http\ImagecacheController;

class ImagecacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/imagecache.php', 'imagecache');

        $this->app->singleton(Imagecache::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/imagecache.php' => config_path('imagecache.php'),
        ], 'imagecache-config');

        if ($this->app->runningInConsole()) {
            $this->commands([ClearCommand::class]);
        }

        $this->registerRoute();
    }

    /**
     * Same URI and route name as intervention/imagecache, so existing
     * image URLs and route('imagecache', ...) calls keep working.
     */
    private function registerRoute(): void
    {
        $prefix = config('imagecache.route');

        if (! is_string($prefix) || trim($prefix, '/') === '') {
            return;
        }

        if (method_exists($this->app, 'routesAreCached') && $this->app->routesAreCached()) {
            return;
        }

        Route::get(trim($prefix, '/').'/{template}/{filename}', [ImagecacheController::class, 'show'])
            ->where('filename', Imagecache::FILENAME_PATTERN)
            ->middleware((array) config('imagecache.middleware', []))
            ->name('imagecache');
    }
}
