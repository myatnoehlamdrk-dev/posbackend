<?php

namespace App\Http\Controllers;

use App\Services\ImageBudget;
use App\Services\ImgBBService;
use App\Services\LocalUploads;
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

        // Local first (the default): the shop's images then live on the same
        // origin that serves the API, which every device in the build is
        // already reaching — no internet hop, no third-party host that can be
        // slow or unreachable while the till keeps selling. ImgBB remains the
        // order of the day under IMAGES_STORE=imgbb, where local storage is
        // only the fallback it used to be.
        if ($this->prefersLocal()) {
            $payload = $this->storeLocally($request, $file, fellBack: false);
            if ($payload !== null) {
                return response()->json($payload, 201);
            }

            Log::warning('Local image storage failed; trying ImgBB instead.');
        }

        try {
            $result = $this->imgbb->upload($this->withinBudget($file), $data['name'] ?? null);

            return response()->json([
                'url' => $result['url'],
                'display_url' => $result['display_url'],
                'delete_url' => $result['delete_url'],
                // The bytes went to ImgBB's CDN; nothing is written to this
                // machine's storage, so deleting a local file is not what
                // removes the image.
                'stored_locally' => false,
                // ImgBB was the primary host and it took the bytes: nothing
                // degraded, so the client has nothing to warn about.
                'fallback' => false,
            ], 201);
        } catch (\Throwable $e) {
            Log::warning('ImgBB upload failed; falling back to local storage', [
                'error' => $e->getMessage(),
            ]);

            $payload = $this->storeLocally($request, $file, fellBack: true);
            if ($payload !== null) {
                return response()->json($payload, 201);
            }

            // Neither host could take the image: surface the original
            // failure rather than a disk error nobody can act on.
            throw $e;
        }
    }

    /** Whether this server's own disk is the primary home for uploads. */
    private function prefersLocal(): bool
    {
        return (string) config('services.images.store', 'local') !== 'imgbb';
    }

    /**
     * Budgets the file, writes it under `storage/app/uploads` and builds the
     * response payload. Null when the bytes could not be written — the caller
     * then decides which host to try next.
     *
     * `$fellBack` marks the legacy path (the primary host failed and local
     * storage caught it). It is the signal the client's "saved on this server
     * only" warning rides on: under local-first storage, keeping the photo on
     * this server is the plan, not a degradation, and warning about every
     * upload would train the warning away.
     */
    private function storeLocally(Request $request, UploadedFile $file, bool $fellBack): ?array
    {
        try {
            // Compressed here, not just moved: this path can carry a file the
            // client could not compress itself (an undecodable format such as
            // HEIC), so the server is the last place the budget can be applied.
            $bytes = (string) file_get_contents($file->getRealPath());
            $fit = ImageBudget::fit($bytes, $this->extensionOf($file));

            $filename = LocalUploads::store($fit['bytes'], $fit['ext']);

            if ($fit['resized']) {
                Log::info('Local image was over the budget; resized before writing.', [
                    'stored' => $fit['stored'],
                    'was_bytes' => strlen($bytes),
                ]);
            }

            // Absolute, not `/uploads/...`: consumers without the Flutter
            // client's URL rewriting (the PDF renderer, a browser, curl) would
            // otherwise be handed a path that only resolves from inside this
            // machine. The client rewrites the host onto its configured
            // origin anyway; this is for everyone else.
            $url = rtrim($request->getSchemeAndHttpHost(), '/').'/uploads/'.$filename;

            return [
                'url' => $url,
                'display_url' => $url,
                'delete_url' => '',
                // The bytes are on this server's disk. Reachable by every
                // device that talks to this origin — see the route in
                // routes/web.php for how they get the CORS header.
                'stored_locally' => true,
                'fallback' => $fellBack,
            ];
        } catch (\Throwable $e) {
            Log::error('Local image storage failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
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
