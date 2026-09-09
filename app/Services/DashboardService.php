<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const CACHE_TTL = 60;

    public function getStats(int $shopId): array
    {
        return Cache::remember("dashboard-stats-{$shopId}", self::CACHE_TTL, function () use ($shopId) {
            $today = Carbon::today();
            $monthStart = Carbon::now()->startOfMonth();

            return [
                'today_sales' => $this->getTodaySales($today),
                'month_sales' => $this->getMonthSales($monthStart),
                'pending_orders' => $this->getPendingOrders(),
                'total_products' => $this->getTotalProducts($shopId),
                'in_stock' => $this->getInStockCount($shopId),
                'low_stock_count' => $this->getLowStockCount($shopId),
                'pending_purchases' => $this->getPendingPurchases(),
                'total_sales' => $this->getAllTimeSales(),
            ];
        });
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

    public function getTopProducts(int $shopId, int $limit = 5): array
    {
        return DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('sale_items.created_at', '>=', Carbon::now()->subDays(30))
            ->where('inventories.shop_id', $shopId)
            ->select('products.name as product_name', DB::raw('SUM(sale_items.quantity) as total_quantity'))
            ->groupBy('products.name')
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

    public function getCategoryTrend(int $shopId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $data = DB::table('categories')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->join('packages', 'packages.category_id', '=', 'categories.id')
            ->join('products', 'products.package_id', '=', 'packages.id')
            ->leftJoin('sale_items', function ($join) use ($startDate) {
                $join->on('sale_items.product_id', '=', 'products.id')
                    ->where('sale_items.created_at', '>=', $startDate);
            })
            ->where('inventories.shop_id', $shopId)
            ->select(
                'categories.name as category_name',
                DB::raw('DATE(sale_items.created_at) as date'),
                DB::raw('COALESCE(SUM(sale_items.quantity), 0) as total_quantity')
            )
            ->groupBy('categories.name', 'date')
            ->orderBy('date')
            ->get()
            ->toArray();

        $grouped = [];
        $allDates = [];
        foreach ($data as $row) {
            $cat = $row->category_name;
            $date = $row->date;
            if ($date && !in_array($date, $allDates)) {
                $allDates[] = $date;
            }
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            if ($date) {
                $grouped[$cat][$date] = $row->total_quantity;
            }
        }
        sort($allDates);

        $result = [];
        foreach ($grouped as $category => $dateData) {
            $values = [];
            foreach ($allDates as $date) {
                $values[] = (int)($dateData[$date] ?? 0);
            }
            $result[] = [
                'category' => $category,
                'values' => $values,
                'dates' => $allDates,
            ];
        }

        return $result;
    }

    public function getLeastProducts(int $shopId, int $limit = 3): array
    {
        return DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('sale_items.created_at', '>=', Carbon::now()->subDays(30))
            ->where('inventories.shop_id', $shopId)
            ->select('products.name as product_name', DB::raw('SUM(sale_items.quantity) as total_quantity'))
            ->groupBy('products.name')
            ->orderBy('total_quantity')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getAll(int $shopId, int $days = 30): array
    {
        return Cache::remember("dashboard-all-{$shopId}-{$days}", self::CACHE_TTL, function () use ($shopId, $days) {
            $today = Carbon::today();
            $monthStart = Carbon::now()->startOfMonth();

            return [
                'stats' => [
                    'today_sales' => $this->getTodaySales($today),
                    'month_sales' => $this->getMonthSales($monthStart),
                    'pending_orders' => $this->getPendingOrders(),
                    'total_products' => $this->getTotalProducts($shopId),
                    'in_stock' => $this->getInStockCount($shopId),
                    'low_stock_count' => $this->getLowStockCount($shopId),
                    'pending_purchases' => $this->getPendingPurchases(),
                    'total_sales' => $this->getAllTimeSales(),
                ],
                'category_trend' => $this->getCategoryTrend($shopId, $days),
                'top_products' => $this->getTopProducts($shopId, 3),
                'least_products' => $this->getLeastProducts($shopId, 3),
            ];
        });
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
        return DB::table('products')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('products.active', true)
            ->where('inventories.shop_id', $shopId)
            ->where('products.stock', '>', 0)
            ->where('products.stock', '<=', 5)
            ->count();
    }

    private function getInStockCount(int $shopId): int
    {
        return DB::table('products')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('products.active', true)
            ->where('inventories.shop_id', $shopId)
            ->where('products.stock', '>', 0)
            ->count();
    }

    private function getAllTimeSales(): int
    {
        return (int) (Sale::sum('grand_total') ?? 0);
    }

    private function getTotalProducts(int $shopId): int
    {
        return DB::table('products')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('products.active', true)
            ->where('inventories.shop_id', $shopId)
            ->count();
    }

    private function getPendingPurchases(): int
    {
        return PurchaseItem::where('status', 'pending')->count();
    }
}
