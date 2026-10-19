# ritterg/laravel-imagecache

On-the-fly image templates for Laravel, served from a file cache. A maintained
drop-in replacement for the abandoned `intervention/imagecache`, built on
`intervention/image` 3 or 4.

- Same URI and route name as `intervention/imagecache`:
  `/{route}/{template}/{filename}`, `route('imagecache', [$template, $filename])`.
  Existing image URLs keep working.
- Reads the existing `config/imagecache.php` (`route`, `paths`, `templates`, `lifetime`).
- Built-in `original` and `download` templates; the legacy template classes
  `Intervention\Image\Templates\Small|Medium|Large` keep working.
- Templates as **operations arrays** (safe for `php artisan config:cache`),
  closures, or classes.
- Rendered images are stored once on disk and re-rendered automatically when the
  source file changes or the lifetime has passed.
- `Cache-Control`, `ETag`, `Last-Modified`, `304 Not Modified`.
- Only files inside the configured paths are served (realpath check), unknown
  templates return 404.

## Requirements

- PHP ≥ 8.1, Laravel 9 – 13
- `intervention/image` ^3.4 (PHP 8.1+) or ^4.0 (PHP 8.3+), GD or Imagick

## Installation

Public GitHub repository, installed via a Composer VCS entry (no authentication
needed, works on shared hosting):

```bash
composer remove intervention/imagecache
composer config repositories.imagecache vcs https://github.com/ritterg/laravel-imagecache.git
composer require ritterg/laravel-imagecache:^1.0
```

The service provider is auto-discovered and registers the route. An existing
`config/imagecache.php` is used as is; to start from scratch:

```bash
php artisan vendor:publish --tag=imagecache-config
```

## Configuration

```php
return [
    'route' => 'imagecache',          // null = register no route
    'middleware' => [],
    'paths' => [
        public_path('img'),
        base_path('assets/images'),
    ],
    'templates' => [
        // operations array: method => arguments (positional or named)
        'small'  => ['cover' => [120, 90]],
        'thumb'  => ['scaleDown' => ['width' => 640]],
        'teaser' => ['scale' => ['width' => 800], 'cover' => [800, 400]],

        // closure (prevents config:cache, like in intervention/imagecache)
        'banner' => fn ($image) => $image->cover(920, 300),

        // class implementing Ritterg\Imagecache\Contracts\Template
        'logo'   => App\Imagecache\Logo::class,
    ],
    'lifetime' => 43200,              // minutes; 0 = until the source changes
    'driver' => env('IMAGECACHE_DRIVER', 'gd'),   // gd | imagick
    'quality' => 90,                  // jpg, webp, avif, heic, jp2
    'cache_path' => storage_path('framework/imagecache'),
];
```

Keys missing from an existing config file fall back to these defaults.

Rendered files are cached under a key of template name, template definition
(for arrays and classes), source path, mtime and size. A changed array or class
template therefore renders anew automatically. **After changing a closure
template run `php artisan imagecache:clear`** (e.g. in the deploy script).
`php artisan imagecache:clear --expired` only removes files past their lifetime.

## Migrating templates from intervention/image 2

Closures and filter classes written for `intervention/imagecache` use the
intervention/image 2 API and must be rewritten. Filter classes
(`Intervention\Image\Filters\FilterInterface`) raise a clear exception.

| intervention/image 2 | operations array (3/4) |
| --- | --- |
| `fit(w, h)` | `['cover' => [w, h]]` |
| `fit(w, h)` + `upsize()` | `['coverDown' => [w, h]]` |
| `fit(w)` | `['cover' => [w, w]]` |
| `widen(w)` | `['scale' => ['width' => w]]` |
| `widen(w)` + `upsize()` | `['scaleDown' => ['width' => w]]` |
| `heighten(h)` | `['scale' => ['height' => h]]` |
| `heighten(h)` + `upsize()` | `['scaleDown' => ['height' => h]]` |
| `resize(w, h)` + `aspectRatio()` | `['scale' => [w, h]]` |
| `resize(w, h)` + `aspectRatio()` + `upsize()` | `['scaleDown' => [w, h]]` |
| `resize(w, h)` (distort) | `['resize' => [w, h]]` |
| `resize(w, h, aspectRatio)` + `resizeCanvas(w, h, 'center', false, '#000')` | `['contain' => [w, h, '000000']]` |

A filter class becomes a template class:

```php
use Intervention\Image\Interfaces\ImageInterface;
use Ritterg\Imagecache\Contracts\Template;

class Logo implements Template
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->scaleDown(width: 300);
    }
}
```

Direct uses of intervention/image 2 elsewhere in the app (`Image::make()`,
`->fit()`, …) need the same rewrite; see the
[intervention/image documentation](https://image.intervention.io/).

## Building URLs

```php
route('imagecache', ['small', 'products/4711.jpg']);
app(Ritterg\Imagecache\Imagecache::class)->url('small', 'products/4711.jpg');
```

## Development

```bash
composer install
composer test      # Pest
composer analyse   # PHPStan / Larastan
```

Tested with Laravel 13 + intervention/image 4 and Laravel 10 + intervention/image 3,
GD and Imagick.

## License

MIT
