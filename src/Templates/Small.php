<?php

namespace Ritterg\Imagecache\Templates;

use Intervention\Image\Interfaces\ImageInterface;
use Ritterg\Imagecache\Contracts\Template;

/**
 * Same result as intervention/imagecache's Intervention\Image\Templates\Small:
 * crop to fill 120x90.
 */
class Small implements Template
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->cover(120, 90);
    }
}
