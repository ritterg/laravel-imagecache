<?php

namespace Ritterg\Imagecache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Ritterg\Imagecache\Contracts\Template;

/**
 * Same result as intervention/imagecache's Intervention\Image\Templates\Large:
 * crop to fill 480x360.
 */
class Large implements Template
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->cover(480, 360);
    }
}
