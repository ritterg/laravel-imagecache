<?php

namespace Ritterg\Imagecache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Ritterg\Imagecache\Contracts\Template;

/**
 * Same result as intervention/imagecache's Intervention\Image\Templates\Medium:
 * crop to fill 240x180.
 */
class Medium implements Template
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->cover(240, 180);
    }
}
