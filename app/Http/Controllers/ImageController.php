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
            'image' => ['required', 'image', 'max:5120'], // up to 5MB
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['image'];

        $result = $this->imgbb->upload($file, $data['name'] ?? null);

        return response()->json([
            'url' => $result['url'],
            'display_url' => $result['display_url'],
            'delete_url' => $result['delete_url'],
        ], 201);
    }
}
