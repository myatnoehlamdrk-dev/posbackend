<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

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
     * @return array{url: string, display_url: string, delete_url: string}
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
        ];
    }

    /**
     * Delete a previously uploaded image using the delete_url returned by ImgBB.
     */
    public function delete(string $deleteUrl): bool
    {
        if (blank($deleteUrl)) {
            return false;
        }

        try {
            Http::get($deleteUrl)->throw();

            return true;
        } catch (\Throwable) {
            return false;
        }
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
