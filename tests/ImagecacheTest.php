<?php

use Illuminate\Support\Facades\Artisan;
use Ritterg\Imagecache\Imagecache;

function body($response): string
{
    $file = $response->baseResponse->getFile();

    return (string) file_get_contents($file->getPathname());
}

it('renders a legacy class-name template like intervention/imagecache', function () {
    $this->makeImage('photo.jpg', 400, 300);

    $response = $this->get('/imagecache/small/photo.jpg')->assertOk();

    [$w, $h] = $this->sizeOf(body($response));
    expect([$w, $h])->toBe([120, 90])
        ->and($response->headers->get('Content-Type'))->toBe('image/jpeg');
});

it('renders the built-in template classes', function () {
    $this->makeImage('photo.jpg', 400, 300);

    [$w, $h] = $this->sizeOf(body($this->get('/imagecache/medium/photo.jpg')));

    expect([$w, $h])->toBe([240, 180]);
});

it('applies an operations array with named arguments', function () {
    $this->makeImage('wide.jpg', 400, 200);

    [$w, $h] = $this->sizeOf(body($this->get('/imagecache/thumb/wide.jpg')));

    expect([$w, $h])->toBe([100, 50]);
});

it('applies several operations in order', function () {
    $this->makeImage('wide.jpg', 400, 200);

    [$w, $h] = $this->sizeOf(body($this->get('/imagecache/twostep/wide.jpg')));

    expect([$w, $h])->toBe([50, 50]);
});

it('accepts closures that return the image or only mutate it', function () {
    $this->makeImage('photo.jpg', 400, 300);

    expect(array_slice($this->sizeOf(body($this->get('/imagecache/banner/photo.jpg'))), 0, 2))->toBe([300, 100])
        ->and(array_slice($this->sizeOf(body($this->get('/imagecache/mutating/photo.jpg'))), 0, 2))->toBe([10, 10]);
});

it('keeps png as png', function () {
    $this->makeImage('logo.png', 400, 300, 'png');

    $response = $this->get('/imagecache/small/logo.png')->assertOk();

    expect($this->sizeOf(body($response))['mime'])->toBe('image/png');
});

it('serves the untouched file for original and download', function () {
    $path = $this->makeImage('photo.jpg', 400, 300);

    $original = $this->get('/imagecache/original/photo.jpg')->assertOk();
    expect(body($original))->toBe(file_get_contents($path));

    $download = $this->get('/imagecache/download/photo.jpg')->assertOk();
    expect($download->headers->get('Content-Disposition'))->toContain('attachment')->toContain('photo.jpg')
        ->and($this->cachedFiles())->toBeEmpty();
});

it('finds images in sub-directories', function () {
    $this->makeImage('sub/inner.jpg', 400, 300);

    $this->get('/imagecache/small/sub/inner.jpg')->assertOk();
});

it('returns 404 for unknown templates and missing files', function () {
    $this->makeImage('photo.jpg', 400, 300);

    $this->get('/imagecache/nope/photo.jpg')->assertNotFound();
    $this->get('/imagecache/small/missing.jpg')->assertNotFound();
});

it('never serves files outside the configured paths', function () {
    file_put_contents($this->root.'/secret/key.jpg', 'secret');

    $this->get('/imagecache/original/../secret/key.jpg')->assertNotFound();
    $this->get('/imagecache/original/sub/../../secret/key.jpg')->assertNotFound();
    expect(app(Imagecache::class)->resolveSource('../secret/key.jpg'))->toBeNull()
        ->and(app(Imagecache::class)->resolveSource($this->root.'/secret/key.jpg'))->toBeNull();
});

it('renders once and then serves from the cache', function () {
    $this->makeImage('photo.jpg', 400, 300);

    $this->get('/imagecache/small/photo.jpg');
    $files = $this->cachedFiles();
    expect($files)->toHaveCount(1);
    $mtime = filemtime($files[0]);

    sleep(1);
    $this->get('/imagecache/small/photo.jpg');
    clearstatcache();

    expect($this->cachedFiles())->toHaveCount(1)
        ->and(filemtime($files[0]))->toBe($mtime);
});

it('renders again when the source image changes', function () {
    $this->makeImage('photo.jpg', 400, 300);
    $this->get('/imagecache/small/photo.jpg');

    $this->makeImage('photo.jpg', 800, 300);
    touch($this->root.'/images/photo.jpg', time() + 5);
    clearstatcache();
    $this->get('/imagecache/small/photo.jpg');

    expect($this->cachedFiles())->toHaveCount(2);
});

it('renders again after the lifetime has passed', function () {
    config(['imagecache.lifetime' => 1]);
    $this->makeImage('photo.jpg', 400, 300);
    $this->get('/imagecache/small/photo.jpg');
    $cached = $this->cachedFiles()[0];
    touch($cached, time() - 120);
    clearstatcache();

    $this->get('/imagecache/small/photo.jpg');
    clearstatcache();

    expect(filemtime($cached))->toBeGreaterThan(time() - 60);
});

it('sends cache headers and answers conditional requests with 304', function () {
    $this->makeImage('photo.jpg', 400, 300);

    $first = $this->get('/imagecache/small/photo.jpg');
    $etag = $first->headers->get('ETag');

    expect($first->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=2592000')
        ->and($etag)->not->toBeEmpty();

    $this->get('/imagecache/small/photo.jpg', ['If-None-Match' => $etag])->assertStatus(304);
});

it('reports a broken template instead of serving garbage', function () {
    $this->makeImage('photo.jpg', 400, 300);

    $this->withoutExceptionHandling();
    $this->get('/imagecache/bogus/photo.jpg');
})->throws(InvalidArgumentException::class, 'unknown image operation [noSuchOperation]');

it('explains that intervention/image 2 filters must be rewritten', function () {
    $filter = new class {
        public function applyFilter($image)
        {
            return $image;
        }
    };
    app()->instance('LegacyFilter', $filter);
    config(['imagecache.templates.legacy' => 'LegacyFilter']);
    $this->makeImage('photo.jpg', 400, 300);

    app(Imagecache::class)->render('legacy', $this->root.'/images/photo.jpg');
})->throws(InvalidArgumentException::class);

it('registers the route under the legacy name and builds urls', function () {
    expect(route('imagecache', ['small', 'a/b.jpg']))->toEndWith('/imagecache/small/a/b.jpg')
        ->and(app(Imagecache::class)->url('small', 'a/b.jpg'))->toEndWith('/imagecache/small/a/b.jpg');
});

it('clears the cache, optionally only expired files', function () {
    $this->makeImage('photo.jpg', 400, 300);
    $this->get('/imagecache/small/photo.jpg');
    $this->get('/imagecache/medium/photo.jpg');

    Artisan::call('imagecache:clear', ['--expired' => true]);
    expect($this->cachedFiles())->toHaveCount(2);

    Artisan::call('imagecache:clear');
    expect($this->cachedFiles())->toBeEmpty();
});

it('supports the README mappings for contain and coverDown', function () {
    $this->makeImage('square.jpg', 400, 400);
    $this->makeImage('tiny.jpg', 200, 100);

    expect(array_slice($this->sizeOf(body($this->get('/imagecache/slider/square.jpg'))), 0, 2))->toBe([100, 50])
        ->and(array_slice($this->sizeOf(body($this->get('/imagecache/nogrow/tiny.jpg'))), 0, 2))->toBe([133, 100]); // cropped to 4:3, never enlarged (like v2 fit + upsize)
});
