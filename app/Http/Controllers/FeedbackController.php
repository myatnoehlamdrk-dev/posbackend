<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:comment,suggestion,bug_report'],
            'user_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shopId = $this->shopId($request);

        if (! $shopId) {
            return $this->noShopResponse();
        }

        return $this->feedbackService->listForShop($request);
    }

    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $this->shopId($request);

        if (! $shopId) {
            return $this->noShopResponse();
        }

        return $this->feedbackService->create($request->validated(), $user->id, $shopId);
    }

    public function show(Request $request, int $feedback): JsonResponse
    {
        $shopId = $this->shopId($request);

        if (! $shopId) {
            return $this->noShopResponse();
        }

        return $this->feedbackService->show($feedback, $shopId);
    }

    public function destroy(Request $request, int $feedback): JsonResponse
    {
        $shopId = $this->shopId($request);

        if (! $shopId) {
            return $this->noShopResponse();
        }

        return $this->feedbackService->delete($feedback, $shopId);
    }

    private function shopId(Request $request): ?int
    {
        $shopId = $request->user()->shop_id;

        return $shopId ? (int) $shopId : null;
    }

    private function noShopResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Feedback requires a shop.',
        ], 422);
    }
}
