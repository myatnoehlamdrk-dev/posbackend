<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getStats(int $shopId): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        return [
            'today_sales' => $this->getTodaySales($today),
            'month_sales' => $this->getMonthSales($monthStart),
            'pending_orders' => $this->getPendingOrders(),
            'low_stock_count' => $this->getLowStockCount($shopId),
            'total_products' => $this->getTotalProducts($shopId),
            'pending_purchases' => $this->getPendingPurchases(),
        ];
    }

    public function getSalesChart(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $sales = Sale::where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(grand_total) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $sales->toArray();
    }

    public function getTopProducts(int $limit = 5): array
    {
        return Sale::select('product_name', DB::raw('SUM(quantity_sold) as total_quantity'))
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('product_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getRecentSales(int $limit = 5)
    {
        return Sale::with('saleItems')
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function getTodaySales(Carbon $today): array
    {
        $result = Sale::where('created_at', '>=', $today)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total')
            ->first();

        return ['count' => $result->count, 'total' => $result->total];
    }

    private function getMonthSales(Carbon $monthStart): array
    {
        $result = Sale::where('created_at', '>=', $monthStart)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total')
            ->first();

        return ['count' => $result->count, 'total' => $result->total];
    }

    private function getPendingOrders(): int
    {
        return Order::where('status', 'draft')->count();
    }

    private function getLowStockCount(int $shopId): int
    {
        $products = Product::where('active', true)
            ->whereHas('package.category.inventory', fn ($q) => $q->where('shop_id', $shopId))
            ->get();

        return $products->filter(fn ($product) => $product->getAvailableStock() <= 5)->count();
    }

    private function getTotalProducts(int $shopId): int
    {
        return Product::where('active', true)
            ->whereHas('package.category.inventory', fn ($q) => $q->where('shop_id', $shopId))
            ->count();
    }

    private function getPendingPurchases(): int
    {
        return PurchaseItem::where('status', 'pending')->count();
    }
}
