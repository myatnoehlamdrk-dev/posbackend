<?php

namespace App\Http\Controllers;

use App\Services\LocalUploads;

class UploadsController extends Controller
{
    /**
     * Serves one locally stored upload with the headers a browser needs.
     *
     * `Access-Control-Allow-Origin: *` is the whole point of this route: the
     * Flutter web build is served from a different origin than the API, and
     * the byte fetch behind `CachedNetworkImage` is an XHR. Without the
     * header the browser fails the request before the file is ever read, and
     * every tile pointing at a local upload parks on its default icon while
     * all API calls work perfectly — which is exactly the bug this replaces
     * (static files in `public/` were answered before middleware could add
     * the header).
     *
     * The cache headers are safe to hold hard because the file name is
     * content-unique: the bytes behind a URL never change.
     */
    public function show(string $filename)
    {
        $path = LocalUploads::path($filename);
        if ($path === null) {
            abort(404);
        }

        return response()->file($path, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
