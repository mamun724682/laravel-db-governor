<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\Response;

class AssetController
{
    private const ASSETS = [
        'alpine.min.js' => 'application/javascript',
        'tailwind.js'   => 'application/javascript',
    ];

    public function serve(string $file): Response
    {
        if (! array_key_exists($file, self::ASSETS)) {
            abort(404);
        }

        $path = __DIR__ . '/../../../public/' . $file;

        if (! file_exists($path)) {
            abort(404);
        }

        $content  = file_get_contents($path);
        $mimeType = self::ASSETS[$file];
        $etag     = md5($content);

        if (request()->header('If-None-Match') === $etag) {
            return response('', 304);
        }

        return response($content, 200)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'public, max-age=31536000, immutable')
            ->header('ETag', $etag);
    }
}


