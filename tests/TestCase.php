<?php

namespace Ritterg\Imagecache\Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Ritterg\Imagecache\ImagecacheServiceProvider;

class TestCase extends Orchestra
{
    protected string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/imagecache-tests-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/images/sub', 0755, true);
        mkdir($this->root.'/secret', 0755, true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [ImagecacheServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('imagecache.paths', [$this->root.'/images']);
        $app['config']->set('imagecache.cache_path', $this->root.'/cache');
        $app['config']->set('imagecache.templates', [
            'small' => 'Intervention\Image\Templates\Small',
            'medium' => \Ritterg\Imagecache\Templates\Medium::class,
            'thumb' => ['scaleDown' => ['width' => 100]],
            'twostep' => ['scale' => ['width' => 200], 'cover' => [50, 50]],
            'banner' => fn ($image) => $image->cover(300, 100),
            'mutating' => function ($image): void {
                $image->cover(10, 10);
            },
            'slider' => ['contain' => [100, 50, '000000']],
            'nogrow' => ['coverDown' => [800, 600]],
            'bogus' => ['noSuchOperation' => [1]],
        ]);
    }

    /**
     * Write a test image of the given size and return its absolute path.
     */
    protected function makeImage(string $relative, int $width, int $height, string $type = 'jpg'): string
    {
        $path = $this->root.'/images/'.$relative;
        $gd = imagecreatetruecolor($width, $height);
        imagefill($gd, 0, 0, (int) imagecolorallocate($gd, 200, 50, 50));

        match ($type) {
            'png' => imagepng($gd, $path),
            'gif' => imagegif($gd, $path),
            default => imagejpeg($gd, $path, 90),
        };

        return $path;
    }

    /**
     * @return array{0:int,1:int,mime:string}
     */
    protected function sizeOf(string $binary): array
    {
        $info = getimagesizefromstring($binary);
        assert($info !== false);

        return $info;
    }

    protected function cachedFiles(): array
    {
        return glob($this->root.'/cache/*') ?: [];
    }
}
