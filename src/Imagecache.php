<?php

namespace Ritterg\Imagecache;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Ritterg\Imagecache\Contracts\Template;

class Imagecache
{
    /** Filename pattern of the route, identical to intervention/imagecache. */
    public const FILENAME_PATTERN = '[ \w\.\/\-\@\(\)\=]+';

    /** Template names that serve the unmodified file. */
    public const BUILT_IN = ['original', 'download'];

    /** Legacy intervention/imagecache template classes and their replacements. */
    private const LEGACY_TEMPLATES = [
        'Intervention\Image\Templates\Small' => Templates\Small::class,
        'Intervention\Image\Templates\Medium' => Templates\Medium::class,
        'Intervention\Image\Templates\Large' => Templates\Large::class,
    ];

    /** Formats that take a quality option when encoding. */
    private const LOSSY_EXTENSIONS = ['jpg', 'jpeg', 'jfif', 'pjpeg', 'webp', 'avif', 'heic', 'heif', 'jp2', 'j2k', 'jpf', 'jpm', 'jpg2', 'j2c', 'jpc', 'jpx'];

    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
    ) {
    }

    /**
     * Absolute path of the source image, or null if it is not inside one of
     * the configured paths. Sub-directories are allowed, escaping is not.
     */
    public function resolveSource(string $filename): ?string
    {
        if ($filename === '' || str_contains($filename, "\0")) {
            return null;
        }

        foreach ((array) $this->config->get('imagecache.paths', []) as $base) {
            $base = realpath((string) $base);
            if ($base === false) {
                continue;
            }

            $candidate = realpath($base.DIRECTORY_SEPARATOR.ltrim($filename, '/'));
            if ($candidate !== false
                && is_file($candidate)
                && str_starts_with($candidate, $base.DIRECTORY_SEPARATOR)) {
                return $candidate;
            }
        }

        return null;
    }

    public function isBuiltIn(string $template): bool
    {
        return in_array(strtolower($template), self::BUILT_IN, true);
    }

    public function hasTemplate(string $template): bool
    {
        return $this->templateDefinition($template) !== null;
    }

    /**
     * Path of the rendered image, rendering it first if there is no valid
     * cached copy. The cache key covers template, source path, mtime and size,
     * so a replaced source file is picked up automatically.
     */
    public function render(string $template, string $source): string
    {
        $definition = $this->templateDefinition($template)
            ?? throw new InvalidArgumentException("Unknown imagecache template [{$template}].");

        $target = $this->cacheFile($template, $definition, $source);

        if ($this->isFresh($target)) {
            return $target;
        }

        $image = $this->decode($source);
        $image = $this->applyTemplate($definition, $image, $template);
        $this->store($image, $target);

        return $target;
    }

    /**
     * Public URL of an image, e.g. Imagecache::url('small', 'logo.png').
     */
    public function url(string $template, string $filename): string
    {
        $route = trim((string) $this->config->get('imagecache.route', 'imagecache'), '/');

        return url($route.'/'.$template.'/'.ltrim($filename, '/'));
    }

    /**
     * Delete rendered images; with $expiredOnly only those past their lifetime.
     * Returns the number of deleted files.
     */
    public function clear(bool $expiredOnly = false): int
    {
        $dir = $this->cachePath();
        if (! is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        foreach (new \FilesystemIterator($dir) as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || $file->getFilename() === '.gitignore') {
                continue;
            }
            if ($expiredOnly && $this->isFresh($file->getPathname())) {
                continue;
            }
            if (@unlink($file->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function lifetimeMinutes(): int
    {
        return max(0, (int) $this->config->get('imagecache.lifetime', 43200));
    }

    /**
     * Closure, class name or operations array; null if not configured.
     */
    private function templateDefinition(string $template): mixed
    {
        $templates = (array) $this->config->get('imagecache.templates', []);

        if (! array_key_exists($template, $templates) || $templates[$template] === null) {
            return null;
        }

        $definition = $templates[$template];

        if (is_string($definition)) {
            return self::LEGACY_TEMPLATES[ltrim($definition, '\\')] ?? $definition;
        }

        return $definition;
    }

    private function applyTemplate(mixed $definition, ImageInterface $image, string $name): ImageInterface
    {
        if ($definition instanceof Closure) {
            $result = $definition($image);

            return $result instanceof ImageInterface ? $result : $image;
        }

        if (is_array($definition)) {
            foreach ($definition as $method => $arguments) {
                if (! is_string($method) || ! method_exists($image, $method)) {
                    throw new InvalidArgumentException("Imagecache template [{$name}]: unknown image operation [".(string) $method.'].');
                }
                $image = $image->{$method}(...(array) $arguments);
            }

            return $image;
        }

        if (is_string($definition) && class_exists($definition)) {
            $instance = $this->container->make($definition);

            if ($instance instanceof Template) {
                return $instance->apply($image);
            }

            if (method_exists($instance, 'applyFilter')) {
                throw new InvalidArgumentException(
                    "Imagecache template [{$name}]: {$definition} is an intervention/image 2 filter. "
                    .'Rewrite it as a '.Template::class.' or an operations array (see README).'
                );
            }
        }

        throw new InvalidArgumentException("Imagecache template [{$name}] is not a closure, operations array or ".Template::class.'.');
    }

    private function cacheFile(string $template, mixed $definition, string $source): string
    {
        $fingerprint = match (true) {
            is_array($definition) => (string) json_encode($definition),
            is_string($definition) => $definition,
            default => 'closure',
        };

        $key = sha1(implode("\0", [$template, $fingerprint, $source, (string) filemtime($source), (string) filesize($source)]));
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'img';

        return $this->cachePath().DIRECTORY_SEPARATOR.$key.'.'.$extension;
    }

    private function isFresh(string $file): bool
    {
        if (! is_file($file)) {
            return false;
        }

        $lifetime = $this->lifetimeMinutes();

        return $lifetime === 0 || (time() - (int) filemtime($file)) < $lifetime * 60;
    }

    private function cachePath(): string
    {
        return rtrim((string) $this->config->get('imagecache.cache_path', storage_path('framework/imagecache')), DIRECTORY_SEPARATOR);
    }

    private function manager(): ImageManager
    {
        $driver = match (strtolower((string) $this->config->get('imagecache.driver', 'gd'))) {
            'imagick' => \Intervention\Image\Drivers\Imagick\Driver::class,
            default => \Intervention\Image\Drivers\Gd\Driver::class,
        };

        return new ImageManager($driver);
    }

    /**
     * intervention/image 4 renamed read() to decodePath().
     */
    private function decode(string $source): ImageInterface
    {
        $manager = $this->manager();
        $method = method_exists($manager, 'decodePath') ? 'decodePath' : 'read';

        return $manager->{$method}($source);
    }

    /**
     * Encode in the format of the cache file's extension and write it
     * atomically. intervention/image 4 renamed encodeByPath() to encodeUsingPath().
     */
    private function store(ImageInterface $image, string $target): void
    {
        $dir = dirname($target);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Imagecache: cannot create cache directory {$dir}.");
        }

        // Keep rendered images out of the host app's repository, whatever its .gitignore says.
        $gitignore = $dir.DIRECTORY_SEPARATOR.'.gitignore';
        if (! is_file($gitignore)) {
            @file_put_contents($gitignore, "*\n!.gitignore\n");
        }

        $options = in_array(strtolower(pathinfo($target, PATHINFO_EXTENSION)), self::LOSSY_EXTENSIONS, true)
            ? ['quality' => (int) $this->config->get('imagecache.quality', 90)]
            : [];

        $method = method_exists($image, 'encodeUsingPath') ? 'encodeUsingPath' : 'encodeByPath';
        $encoded = $image->{$method}($target, ...$options);

        $tmp = $target.'.'.bin2hex(random_bytes(6)).'.tmp';
        file_put_contents($tmp, (string) $encoded);
        rename($tmp, $target);
    }
}
