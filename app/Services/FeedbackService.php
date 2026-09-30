<?php

namespace App\Services;

use App\Http\Resources\FeedbackResource;
use App\Models\Feedback;
use App\Repositories\Contracts\FeedbackRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackService
{
    public function __construct(
        protected FeedbackRepositoryInterface $feedbackRepository,
    ) {}

    public function listForShop(Request $request): JsonResponse
    {
        $paginator = $this->feedbackRepository->listForShop($request);

        return response()->json([
            'data' => FeedbackResource::collection($paginator),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function show(int $id, int $shopId): JsonResponse
    {
        $feedback = $this->feedbackRepository->findForShop($id, $shopId);

        if (! $feedback) {
            return response()->json(['message' => 'Feedback not found'], 404);
        }

        return response()->json(new FeedbackResource($feedback));
    }

    public function create(array $data, int $userId, int $shopId): JsonResponse
    {
        $feedback = $this->feedbackRepository->create([
            'user_id' => $userId,
            'shop_id' => $shopId,
            'type' => $data['type'] ?? Feedback::TYPE_COMMENT,
            'message' => $data['message'],
        ]);

        return response()->json(new FeedbackResource($feedback), 201);
    }

    public function delete(int $id, int $shopId): JsonResponse
    {
        $feedback = $this->feedbackRepository->findForShop($id, $shopId);

        if (! $feedback) {
            return response()->json(['message' => 'Feedback not found'], 404);
        }

        $this->feedbackRepository->delete($feedback);

        return response()->json(null, 204);
    }
}
