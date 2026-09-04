<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Order;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $modelType = $request->input('type');
        $logs = $this->auditService->getForShop($request->user()->shop_id, $modelType);
        return response()->json($logs);
    }

    public function forSale(Sale $sale): JsonResponse
    {
        $logs = $this->auditService->getForModel($sale);
        return response()->json($logs);
    }

    public function forOrder(Order $order): JsonResponse
    {
        $logs = $this->auditService->getForModel($order);
        return response()->json($logs);
    }

    public function recent(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        $logs = $this->auditService->getRecentActivity($request->user()->shop_id, $limit);
        return response()->json($logs);
    }
}
