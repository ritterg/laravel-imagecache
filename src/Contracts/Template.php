<?php

namespace Ritterg\Imagecache\Contracts;

use Intervention\Image\Interfaces\ImageInterface;

interface Template
{
    /**
     * Apply the template to the image and return the result.
     */
    public function apply(ImageInterface $image): ImageInterface;
}
