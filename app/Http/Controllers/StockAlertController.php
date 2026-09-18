<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAlertRequest;
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

    public function store(StoreStockAlertRequest $request, Product $product): JsonResponse
    {
        $alert = $this->stockAlertService->upsert($product->id, $request->user()->shop_id, $request->validated());
        return response()->json($alert, 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->stockAlertService->delete($product->id, $request->user()->shop_id);
        return response()->json(null, 204);
    }

    public function check(Request $request): JsonResponse
    {
        $notifications = $this->stockAlertService->checkAndNotify($request->user()->shop_id);
        return response()->json(['alerts' => $notifications]);
    }
}
