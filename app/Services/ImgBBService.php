<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImgBBService
{
    /** Bounded on both ends: how long to wait for the socket, then for the transfer. */
    protected const CONNECT_TIMEOUT = 5;

    protected const TIMEOUT = 20;

    /** One retry, for transport failures only -- see [upload()]. */
    protected const UPLOAD_ATTEMPTS = 2;

    protected string $apiKey;

    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.imgbb.api_key');
        $this->baseUrl = (string) config('services.imgbb.base_url', 'https://api.imgbb.com/1/upload');
    }

    /**
     * Upload an image to ImgBB.
     *
     * ImgBB is the first choice for every image the app stores, so a stalled
     * host used to stall the save itself: neither timeout was set, and the
     * logs filled with `cURL error 28` while the request waited. A connection
     * failure or an ImgBB 5xx is retried once; anything else (a bad key, a
     * rejected payload) is thrown straight away, because the second attempt
     * would fail the same way and only add the delay.
     *
     * @param  UploadedFile|string  $image  An UploadedFile (binary) or a base64/data-URI/remote-URL string.
     * @return array{url: string, display_url: string, delete_url: string, delete_hash: string}
     *
     * @throws RequestException
     */
    public function upload(UploadedFile|string $image, ?string $name = null): array
    {
        $attempt = 0;
        $last = null;

        while ($attempt < self::UPLOAD_ATTEMPTS) {
            $attempt++;

            try {
                return $this->attemptUpload($image, $name);
            } catch (\Throwable $e) {
                $last = $e;

                $retryable = $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError());

                Log::warning('ImgBB upload attempt failed', [
                    'attempt' => $attempt,
                    'retryable' => $retryable,
                    'error' => $e->getMessage(),
                ]);

                if (! $retryable) {
                    break;
                }
            }
        }

        throw $last;
    }

    /** One attempt, with the timeouts every caller was previously relying on luck for. */
    protected function attemptUpload(UploadedFile|string $image, ?string $name): array
    {
        $client = Http::acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT)
            ->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ]);

        if ($image instanceof UploadedFile) {
            $response = $client
                ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
                ->post($this->baseUrl, array_filter([
                    'key' => $this->apiKey,
                    'name' => $name,
                ]));
        } else {
            $response = $client
                ->asForm()
                ->post($this->baseUrl, array_filter([
                    'key' => $this->apiKey,
                    'image' => $this->normalizeImageString($image),
                    'name' => $name,
                ]));
        }

        $response->throw();

        $data = $response->json('data') ?? [];

        return [
            'url' => $data['url'] ?? throw new RequestException($response),
            'display_url' => $data['display_url'] ?? $data['url'],
            'delete_url' => $data['delete_url'] ?? '',
            'delete_hash' => $data['delete_hash'] ?? '',
        ];
    }

    /**
     * Delete an image by its delete hash using the ImgBB API.
     */
    public function delete(string $deleteUrl, ?string $deleteHash = null): bool
    {
        if (blank($deleteUrl) && blank($deleteHash)) {
            return false;
        }

        $hash = $deleteHash ?? $this->extractDeleteHash($deleteUrl);

        if (blank($hash)) {
            Log::warning('ImgBB delete: could not extract delete hash', ['delete_url' => $deleteUrl]);
            return false;
        }

        try {
            $response = Http::acceptJson()
                // Hard-capped: this can run inline on product delete, and a
                // default 30s HTTP timeout is what used to make a slow ImgBB
                // look like a hung save.
                ->connectTimeout(5)
                ->timeout(15)
                ->withOptions([
                    'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
                ])
                ->asForm()
                ->post('https://api.imgbb.com/1/image/delete', [
                    'key' => $this->apiKey,
                    'delete_hash' => $hash,
                ]);

            $response->throw();

            Log::info('ImgBB image deleted', ['delete_hash' => $hash]);

            return true;
        } catch (\Throwable $e) {
            Log::error('ImgBB image deletion failed', [
                'delete_hash' => $hash,
                'error' => $e->getMessage(),
                // Only an HTTP failure carries a response. A connection
                // failure has no ->response, and reading it raised a second
                // error inside the handler written to contain the first --
                // which is what those `Undefined property: ...ConnectionException::$response`
                // log entries were.
                'response' => $e instanceof RequestException ? $e->response->body() : null,
            ]);
            return false;
        }
    }

    /**
     * Extract the delete hash from a delete_url like
     * https://ibb.co/wNhGRgfn/b90d5a335617e7de0432bb8a3d409a08
     */
    protected function extractDeleteHash(string $deleteUrl): ?string
    {
        $path = parse_url($deleteUrl, PHP_URL_PATH);
        if ($path === null || $path === false) {
            return null;
        }

        $segments = array_filter(explode('/', trim($path, '/')));

        return end($segments) ?: null;
    }

    /**
     * Strip a "data:image/...;base64," prefix if present so ImgBB receives raw base64.
     */
    protected function normalizeImageString(string $image): string
    {
        if (preg_match('/^data:image\/[a-zA-Z+]+;base64,/', $image)) {
            return preg_replace('/^data:image\/[a-zA-Z+]+;base64,/', '', $image);
        }

        return $image;
    }
}
