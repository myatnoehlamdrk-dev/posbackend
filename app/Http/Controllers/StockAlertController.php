<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\StockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    public function __construct(
        private readonly StockAlertService $stockAlertService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $alerts = $this->stockAlertService->listForShop($request->user()->shop_id);
        return response()->json($alerts);
    }

    public function triggered(Request $request): JsonResponse
    {
        $alerts = $this->stockAlertService->getTriggeredAlerts($request->user()->shop_id);
        return response()->json($alerts);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'threshold' => ['nullable', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $alert = $this->stockAlertService->upsert($product->id, $request->user()->shop_id, $data);
        return response()->json($alert, 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->stockAlertService->delete($product->id, $request->user()->shop_id);
        return response()->json(['message' => 'Stock alert deleted.']);
    }

    public function check(Request $request): JsonResponse
    {
        $notifications = $this->stockAlertService->checkAndNotify($request->user()->shop_id);
        return response()->json(['alerts' => $notifications]);
    }
}
