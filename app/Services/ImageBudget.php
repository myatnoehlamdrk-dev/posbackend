<?php

namespace App\Services;

/**
 * Puts a stored image inside the same budget the Flutter client's
 * `compressImage` enforces — longest side at most [MAX_EDGE], bytes at most
 * [BUDGET_BYTES] — for the two paths that arrive at the server already
 * encoded and cannot be re-compressed there by anything else.
 *
 * Those two paths are why `public/uploads` grew a 3.4MB registration logo and
 * 300–700KB product fallbacks: the client only skips compression when it
 * cannot decode the file (HEIC and friends), and the ImgBB fallback then wrote
 * whatever it was handed, byte for byte. A server that can decode has no such
 * hole.
 *
 * GD does the work, so [available] gates everything: without the extension the
 * input is returned untouched and the caller falls back to rejecting oversized
 * uploads rather than storing them. Nothing here ever throws — an image the
 * server cannot decode is stored as it arrived, because losing a photo is
 * worse than storing a fat one.
 */
class ImageBudget
{
    /** Longest side, matching `compressImage(maxEdge: 1280)`. */
    public const MAX_EDGE = 1280;

    /** 200KB, matching `_sizeBudgetBytes` in `image_compressor.dart`. */
    public const BUDGET_BYTES = 200 * 1024;

    /** The canvas steps tried while hunting the budget. Never an upscale. */
    public const EDGES = [960, 720];

    /** JPEG qualities in try-order, matching `_jpegQualities`. */
    public const QUALITIES = [80, 70, 60, 45];

    public static function available(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * Shrink [binary] to the budget.
     *
     * @param  string  $binary  raw image bytes as they arrived.
     * @param  string  $ext  extension of the input ('jpg', 'png', ...), used
     *                       when the bytes cannot be decoded at all.
     * @return array{bytes: string, ext: string, resized: bool, stored: string}
     */
    public static function fit(
        string $binary,
        string $ext = 'jpg',
        int $maxEdge = self::MAX_EDGE,
        int $budgetBytes = self::BUDGET_BYTES,
    ): array {
        if ($binary === '' || ! self::available()) {
            return self::result($binary, $ext, false);
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            // Undecodable here too (HEIC, a truncated file): the original is
            // still a valid upload, just an unoptimised one.
            return self::result($binary, $ext, false);
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // Already inside the budget in both dimensions — nothing to gain, and
        // a re-encode would only burn CPU and lose a little quality. The same
        // early-out `compressImage` does for a small file.
        if (strlen($binary) <= $budgetBytes && max($width, $height) <= $maxEdge) {
            imagedestroy($source);

            return self::result($binary, $ext, false);
        }

        $alpha = self::usesAlpha($source);

        // Opaque pixels go to JPEG — including a photo served as PNG, which
        // never fits the budget losslessly at any sane pixel size. Art that
        // actually uses its alpha channel stays PNG, because JPEG has nowhere
        // to keep it and would hand someone's logo a white box.
        $outExt = $alpha ? 'png' : 'jpg';

        $best = null;
        $bestLength = PHP_INT_MAX;
        $bestResized = false;

        foreach (self::candidates($source, $width, $height, $maxEdge) as $canvas) {
            $resized = $canvas !== $source;
            // zlib level, not a quality: 6 is the fast default and 9 the
            // smaller one, so 6 is tried first — a level-0 (stored) encode is
            // never smaller than either and would only cost a full-resolution
            // buffer per candidate.
            $qualities = $outExt === 'png' ? [6, 9] : self::QUALITIES;

            foreach ($qualities as $quality) {
                $bytes = $outExt === 'png'
                    ? self::encodePng($canvas, $quality)
                    : self::encodeJpeg($canvas, $quality);

                if ($bytes === null) {
                    continue;
                }

                if (strlen($bytes) < $bestLength) {
                    $best = $bytes;
                    $bestLength = strlen($bytes);
                    $bestResized = $resized;
                }

                if ($bestLength <= $budgetBytes) {
                    break 2;
                }
            }

            if ($resized) {
                imagedestroy($canvas);
            }
        }

        imagedestroy($source);

        if ($best === null) {
            // Encoding failed outright; the untouched original is the only
            // image we still have.
            return self::result($binary, $ext, false);
        }

        // Never grow: a re-encode that did not beat the original hands back
        // the original, exactly as the client's compressor does.
        if (strlen($best) >= strlen($binary)) {
            return self::result($binary, $ext, false);
        }

        return self::result($best, $outExt, $bestResized);
    }

    /**
     * The canvas ladder: the image at [MAX_EDGE] (or untouched, if it already
     * fits), then each budget step below it — longest side only, aspect
     * preserved, never an upscale.
     *
     * The oversized image is scaled *before* the first encode rather than
     * after a failed one, which is what `compressImage` does too: a picture
     * over the canvas is never handed to the encoder at full size just to find
     * out it is too big.
     *
     * @return array<int, \GdImage>
     */
    private static function candidates(\GdImage $source, int $width, int $height, int $maxEdge): array
    {
        $original = max($width, $height);
        $base = $original <= $maxEdge
            ? $source
            : self::resize($source, $maxEdge, $width, $height);

        $out = [$base];
        $previous = $base === $source ? $original : $maxEdge;

        foreach (self::EDGES as $edge) {
            if ($edge >= $previous) {
                continue;
            }
            $out[] = self::resize($source, $edge, $width, $height);
            $previous = $edge;
        }

        return $out;
    }

    private static function resize(\GdImage $source, int $edge, int $width, int $height): \GdImage
    {
        $scale = $edge / max($width, $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Blending stays *off* for the copy: with it on, GD composites the
        // source over the new background and the alpha channel the artwork
        // arrived with is flattened away — which is how a transparent logo
        // comes out of a resize as a black box. `savealpha` is what makes the
        // encoder keep those bytes at all; a freshly created truecolor image
        // defaults to discarding them.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height,
        );

        return $canvas;
    }

    /**
     * Whether any sampled pixel is not fully opaque.
     *
     * GD has no cheap "has transparency" query, and reading every pixel of a
     * 12MP canvas in PHP is a request-blocking scan, so a grid is sampled —
     * enough that any logo or cut-out is caught, few enough to cost nothing.
     * GD's alpha runs 0 (opaque) to 127 (invisible), so *any* non-zero value
     * counts; a check for values below 127 would miss exactly the fully
     * transparent pixels that matter most.
     */
    private static function usesAlpha(\GdImage $image): bool
    {
        if (! imageistruecolor($image)) {
            // Palette images keep transparency in the palette itself; walk the
            // entries rather than sampling, which would read indices, not
            // colours.
            $total = imagecolorstotal($image);
            for ($index = 0; $index < $total; $index++) {
                if ((imagecolorsforindex($image, $index)['alpha'] ?? 0) > 0) {
                    return true;
                }
            }

            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int) sqrt(($width * $height) / 20000));

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function encodeJpeg(\GdImage $canvas, int $quality): ?string
    {
        ob_start();
        $ok = imagejpeg($canvas, null, $quality);

        return $ok ? (string) ob_get_clean() : self::discard();
    }

    /** The argument is a zlib level here, not a quality: cheaper to try than to reason about. */
    private static function encodePng(\GdImage $canvas, int $level): ?string
    {
        // An image brought in with `imagecreatefromstring` never arrives with
        // `savealpha` on (it defaults to off, and the resize canvas is the one
        // place it is set). Without this the encoder silently drops the alpha
        // channel — the transparent artwork becomes an opaque block, which is
        // the whole reason PNG is kept in the first place.
        imagesavealpha($canvas, true);

        ob_start();
        $ok = imagepng($canvas, null, $level);

        return $ok ? (string) ob_get_clean() : self::discard();
    }

    private static function discard(): ?string
    {
        ob_end_clean();

        return null;
    }

    /**
     * @return array{bytes: string, ext: string, resized: bool, stored: string}
     */
    private static function result(string $bytes, string $ext, bool $resized): array
    {
        $ext = strtolower(trim($ext));

        if ($ext === '' || $ext === 'jpeg') {
            $ext = 'jpg';
        }

        return [
            'bytes' => $bytes,
            'ext' => $ext,
            'resized' => $resized,
            'stored' => number_format(strlen($bytes) / 1024, 0).'KB',
        ];
    }
}
