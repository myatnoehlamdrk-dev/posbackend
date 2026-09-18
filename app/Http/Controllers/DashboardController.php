<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->dashboardService->getStats($request->user()->shop_id));
    }

    public function salesChart(Request $request): JsonResponse
    {
        $days = $request->integer('days', 7);
        return response()->json($this->dashboardService->getSalesChart($days));
    }

    public function topProducts(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 5);
        return response()->json($this->dashboardService->getTopProducts($request->user()->shop_id, $limit));
    }

    public function recentSales(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 5);
        return response()->json($this->dashboardService->getRecentSales($limit));
    }

    public function categoryTrend(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        return response()->json($this->dashboardService->getCategoryTrend($request->user()->shop_id, $days));
    }

    public function leastProducts(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 3);
        return response()->json($this->dashboardService->getLeastProducts($request->user()->shop_id, $limit));
    }

    public function all(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->shop_id;
        if (empty($shopId)) {
            return response()->json([
                'stats' => [
                    'today_sales' => ['count' => 0, 'total' => 0],
                    'month_sales' => ['count' => 0, 'total' => 0],
                    'pending_orders' => 0,
                    'total_products' => 0,
                    'in_stock' => 0,
                    'low_stock_count' => 0,
                    'pending_purchases' => 0,
                    'total_sales' => 0,
                ],
                'category_trend' => [],
                'top_products' => [],
                'least_products' => [],
            ]);
        }

        $days = $request->integer('days', 30);

        return response()->json($this->dashboardService->getAll($shopId, $user->id, $days));
    }
}
