<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImgBBService
{
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
     * @param  UploadedFile|string  $image  An UploadedFile (binary) or a base64/data-URI/remote-URL string.
     * @return array{url: string, display_url: string, delete_url: string, delete_hash: string}
     *
     * @throws RequestException
     */
    public function upload(UploadedFile|string $image, ?string $name = null): array
    {
        if ($image instanceof UploadedFile) {
            $response = Http::acceptJson()
                ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
                ->post($this->baseUrl, array_filter([
                    'key' => $this->apiKey,
                    'name' => $name,
                ]));
        } else {
            $response = Http::acceptJson()
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
                ->asForm()
                ->post('https://api.imgbb.com/1/image/delete', [
                    'key' => $this->apiKey,
                    'delete_hash' => $hash,
                ]);

            $response->throw();

            Log::info('ImgBB image deleted successfully', [
                'delete_hash' => $hash,
                'response' => $response->json(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('ImgBB image deletion failed', [
                'delete_hash' => $hash,
                'error' => $e->getMessage(),
                'response' => $e->response?->body(),
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
