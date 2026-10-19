<?php

namespace App\Http\Controllers;

use App\Services\ImageBudget;
use App\Services\ImgBBService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ImageController extends Controller
{
    public function __construct(
        private readonly ImgBBService $imgbb
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // A hard stop rather than a quality rule: the bytes are already
            // compressed by the Flutter client before they get here, and
            // anything still above this is a request nobody should be asked
            // to hold. The *budget* is enforced by ImageBudget, where it can
            // shrink a file instead of refusing it.
            'image' => ['required', 'image', 'max:5120'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['image'];

        try {
            $result = $this->imgbb->upload($this->withinBudget($file), $data['name'] ?? null);

            return response()->json([
                'url' => $result['url'],
                'display_url' => $result['display_url'],
                'delete_url' => $result['delete_url'],
                // The bytes went to ImgBB's CDN; nothing is written to this
                // machine's `public/uploads`, so deleting the local file is
                // not what removes the image. Surfaced so the client can tell
                // the user the photo was *not* kept locally.
                'stored_locally' => false,
            ], 201);
        } catch (\Throwable $e) {
            // ImgBB unreachable/invalid: fall back to local storage so the
            // image is still saved and a usable URL is returned.
            Log::warning('ImgBB upload failed; falling back to local storage', [
                'error' => $e->getMessage(),
            ]);

            // Compressed here, not just moved: this is the path that grew
            // `public/uploads` with 300KB–3.4MB files. It is reached exactly
            // when the client could not compress the image itself (an
            // undecodable format such as HEIC), so the server is the last
            // place the budget can be applied.
            $bytes = (string) file_get_contents($file->getRealPath());
            $fit = ImageBudget::fit($bytes, $this->extensionOf($file));

            $dir = public_path('uploads');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = uniqid('img_', true) . '.' . $fit['ext'];
            file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, $fit['bytes']);

            // Absolute, not `/uploads/...`: the device that fetches this later
            // is usually not the one holding the file (the database is shared,
            // `public/uploads` is not), and every consumer without the
            // Flutter client's URL rewriting -- the PDF renderer, a browser,
            // curl -- would otherwise be handed a path that only resolves from
            // inside this machine.
            $url = rtrim($request->getSchemeAndHttpHost(), '/') . '/uploads/' . $filename;

            if ($fit['resized']) {
                Log::info('Local image fallback resized to the budget.', [
                    'stored' => $fit['stored'],
                    'was_bytes' => strlen($bytes),
                ]);
            }

            return response()->json([
                'url' => $url,
                'display_url' => $url,
                'delete_url' => '',
                // This is the case the warning exists for: the bytes are on
                // this machine only, and live only as long as it does.
                'stored_locally' => true,
            ], 201);
        }
    }

    /**
     * Returns the upload itself when it is already inside the budget, and the
     * resized bytes (as a base64 payload, which ImgBB accepts) when it is not.
     *
     * Only the resized case switches encoding, so a normal photo still goes up
     * as the original multipart upload rather than paying for base64.
     */
    private function withinBudget(UploadedFile $file): UploadedFile|string
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $fit = ImageBudget::fit($bytes, $this->extensionOf($file));

        if (! $fit['resized']) {
            return $file;
        }

        Log::info('Upload was over the budget; shrinking before sending to ImgBB.', [
            'was_bytes' => strlen($bytes),
            'stored' => $fit['stored'],
        ]);

        return base64_encode($fit['bytes']);
    }

    /** The client's extension, sanitised — it is used to name a file on disk. */
    private function extensionOf(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return preg_replace('/[^a-z0-9]/', '', $extension) ?: 'jpg';
    }
}
