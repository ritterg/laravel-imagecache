<?php

namespace Ritterg\Imagecache\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ritterg\Imagecache\Imagecache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ImagecacheController extends Controller
{
    public function show(Request $request, Imagecache $imagecache, string $template, string $filename): BinaryFileResponse
    {
        $source = $imagecache->resolveSource($filename);
        abort_if($source === null, 404);

        if ($imagecache->isBuiltIn($template)) {
            $path = $source;
        } else {
            abort_unless($imagecache->hasTemplate($template), 404);
            $path = $imagecache->render($template, $source);
        }

        $response = new BinaryFileResponse($path, 200, [], true, null, false, true);

        $lifetime = $imagecache->lifetimeMinutes();
        $response->setMaxAge($lifetime > 0 ? $lifetime * 60 : 31536000);
        $response->setEtag(sha1($path.'|'.filemtime($path).'|'.filesize($path)));

        if (strtolower($template) === 'download') {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($source));
        }

        $response->isNotModified($request);

        return $response;
    }
}
