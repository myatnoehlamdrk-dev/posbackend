<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->dashboardService->getStats($user->shop_id, $user->id));
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

    public function monthlySales(Request $request): JsonResponse
    {
        $shopId = $request->user()->shop_id;
        $year = $request->integer('year', (int) date('Y'));

        return response()->json([
            'year' => $year,
            'years' => $this->dashboardService->getSalesYears($shopId),
            'months' => $this->dashboardService->getMonthlySales($shopId, $year),
        ]);
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
                'category_distribution' => [],
                'category_quantity' => [],
                'top_products' => [],
                'least_products' => [],
                'no_bought_products' => [],
            ]);
        }

        $days = $request->integer('days', 30);

        return response()->json($this->dashboardService->getAll($shopId, $user->id, $days));
    }

    /**
     * The six tables behind the dashboard "View all" links. Each one is scoped
     * the same way as the card that opens it, so the row count agrees with the
     * number on the card.
     *
     * Every table is paginated in SQL and the client walks it one page at a time
     * as the user scrolls, so the page size is clamped to keep one response a
     * reasonable size.
     */
    public function productsTable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->shop_id)) {
            return $this->emptyTable();
        }

        return $this->tableResponse(
            $this->dashboardService->paginateProductsTable(
                $user->shop_id,
                $user->id,
                $this->page($request),
                $this->perPage($request),
            )
        );
    }

    public function stockTable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->shop_id)) {
            return $this->emptyTable();
        }

        $lowOnly = $request->boolean('low');

        return $this->tableResponse(
            $this->dashboardService->paginateStockTable(
                $user->shop_id,
                $user->id,
                $this->page($request),
                $this->perPage($request),
                $lowOnly,
            )
        );
    }

    public function salesTable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->shop_id)) {
            return $this->emptyTable();
        }

        $data = $request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        return $this->tableResponse(
            $this->dashboardService->paginateSalesTable(
                $user->shop_id,
                $this->page($request),
                $this->perPage($request),
                $data['month'] ?? null,
            )
        );
    }

    public function boughtProductsTable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->shop_id)) {
            return $this->emptyTable();
        }

        return $this->tableResponse(
            $this->dashboardService->paginateBoughtProductsTable(
                $user->shop_id,
                $user->id,
                $this->page($request),
                $this->perPage($request),
                $request->input('direction', 'desc'),
            )
        );
    }

    public function noBoughtProductsTable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->shop_id)) {
            return $this->emptyTable();
        }

        return $this->tableResponse(
            $this->dashboardService->paginateNoBoughtProductsTable(
                $user->shop_id,
                $user->id,
                $this->page($request),
                $this->perPage($request),
            )
        );
    }

    private function page(Request $request): int
    {
        return max(1, $request->integer('page', 1));
    }

    private function perPage(Request $request): int
    {
        return min(100, max(5, $request->integer('per_page', 15)));
    }

    /**
     * Serves a "View all" table as `{ data, meta }`. LengthAwarePaginator
     * serializes flat by default, and the rest of the app historically returns
     * either this shape or a flat one, but the dashboard client reads `meta`,
     * so the paginator is re-wrapped here to keep the contract in one place.
     */
    private function tableResponse(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    private function emptyTable(): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
        ]);
    }
}
