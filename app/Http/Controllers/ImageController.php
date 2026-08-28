<?php

namespace App\Http\Controllers;

use App\Services\ImgBBService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ImageController extends Controller
{
    public function __construct(
        private readonly ImgBBService $imgbb
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image'], // accepts large images
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['image'];

        try {
            $result = $this->imgbb->upload($file, $data['name'] ?? null);

            return response()->json([
                'url' => $result['url'],
                'display_url' => $result['display_url'],
                'delete_url' => $result['delete_url'],
            ], 201);
        } catch (\Throwable) {
            // ImgBB unreachable/invalid: fall back to local storage so the
            // image is still saved and a usable URL is returned.
            $dir = public_path('uploads');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = uniqid('img_', true) . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $filename);
            $url = rtrim(config('app.url'), '/') . '/uploads/' . $filename;

            return response()->json([
                'url' => $url,
                'display_url' => $url,
                'delete_url' => '',
            ], 201);
        }
    }
}
