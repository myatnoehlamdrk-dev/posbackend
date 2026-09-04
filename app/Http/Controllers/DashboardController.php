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
        return response()->json($this->dashboardService->getTopProducts($limit));
    }

    public function recentSales(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 5);
        return response()->json($this->dashboardService->getRecentSales($limit));
    }
}
