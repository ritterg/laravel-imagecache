<?php

return [

    /*
     * URI prefix of the image route: {route}/{template}/{filename}.
     * Set to null to register no route (e.g. to define it yourself).
     */
    'route' => 'imagecache',

    /*
     * Middleware applied to the image route.
     */
    'middleware' => [],

    /*
     * Directories searched, in order, for the requested filename.
     * The filename may contain sub-directories below these paths.
     */
    'paths' => [
        // public_path('img'),
        // storage_path('app/images'),
    ],

    /*
     * Named templates. "original" and "download" are built in and serve the
     * unmodified file (download as an attachment). A template is one of:
     *
     *  - an array of image operations, applied in order (config:cache safe):
     *        'small'  => ['cover' => [120, 90]],
     *        'thumb'  => ['scaleDown' => ['width' => 640]],
     *  - a closure receiving an Intervention\Image\Interfaces\ImageInterface:
     *        'banner' => fn ($image) => $image->cover(920, 300),
     *  - a class name implementing Ritterg\Imagecache\Contracts\Template.
     *    The legacy names Intervention\Image\Templates\Small|Medium|Large
     *    keep working (120x90, 240x180, 480x360).
     */
    'templates' => [
        'small' => Ritterg\Imagecache\Templates\Small::class,
        'medium' => Ritterg\Imagecache\Templates\Medium::class,
        'large' => Ritterg\Imagecache\Templates\Large::class,
    ],

    /*
     * Minutes a rendered image is kept before it is rendered again, also used
     * for the browser Cache-Control max-age. 0 = keep until the source changes.
     */
    'lifetime' => 43200,

    /*
     * Image driver: "gd" or "imagick".
     */
    'driver' => env('IMAGECACHE_DRIVER', 'gd'),

    /*
     * Encoding quality (1-100) for lossy formats (jpg, webp, avif, heic, jp2).
     */
    'quality' => 90,

    /*
     * Where rendered images are stored.
     */
    'cache_path' => storage_path('framework/imagecache'),

];
