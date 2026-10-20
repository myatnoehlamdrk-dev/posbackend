<?php

namespace App\Services;

/**
 * Local storage for uploaded images (the API host's own disk).
 *
 * Files live in `storage/app/uploads` rather than `public/uploads` on purpose:
 * a file inside `public/` is answered by the web server before any Laravel
 * route or middleware runs, so it can never carry the
 * `Access-Control-Allow-Origin` header a Flutter *web* build needs to read the
 * bytes cross-origin. Every file in this directory is served through
 * `UploadsController` instead, which sets that header (and the cache headers)
 * on the way out.
 *
 * The stored file name is content-unique (`uniqid()`), so a URL never changes
 * bytes underneath a cache and can be held immutable.
 */
class LocalUploads
{
    /** The directory every local upload is written to and served from. */
    public static function directory(): string
    {
        $directory = storage_path('app/uploads');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    /**
     * Writes the bytes and returns the stored file name (`img_…`/`shop_…`).
     *
     * @throws \Throwable when the directory or the file itself cannot be
     *                    written — callers decide whether that is fatal or
     *                    whether another host should get the bytes instead.
     */
    public static function store(string $bytes, string $extension, string $prefix = 'img_'): string
    {
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower($extension)) ?: 'jpg';
        $filename = uniqid($prefix, true).'.'.$extension;
        file_put_contents(self::directory().DIRECTORY_SEPARATOR.$filename, $bytes);

        return $filename;
    }

    /**
     * Absolute path for a requested file name, or null when the name is not a
     * plain file name or the file is not there.
     *
     * The name is checked rather than the joined path: a request for
     * `/uploads/../.env` must never resolve inside this directory's parent.
     */
    public static function path(string $filename): ?string
    {
        if ($filename === '' || basename($filename) !== $filename) {
            return null;
        }
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $filename) || str_contains($filename, '..')) {
            return null;
        }

        $path = self::directory().DIRECTORY_SEPARATOR.$filename;

        return is_file($path) ? $path : null;
    }
}
