<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentDashboardRepository implements DashboardRepositoryInterface
{
    public function getTodaySales(int $shopId, Carbon $today): array
    {
        $result = Sale::where('created_at', '>=', $today)
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total')
            ->first();

        return ['count' => $result->count, 'total' => $result->total];
    }

    public function getMonthSales(int $shopId, Carbon $monthStart): array
    {
        $result = Sale::where('created_at', '>=', $monthStart)
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total')
            ->first();

        return ['count' => $result->count, 'total' => $result->total];
    }

    public function getPendingOrders(int $shopId): int
    {
        return Order::where('status', 'draft')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->count();
    }

    public function getTotalProducts(int $shopId): int
    {
        return DB::table('products')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('products.active', true)
            ->where('inventories.shop_id', $shopId)
            ->count();
    }

    public function getInStockCount(int $shopId): int
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

    public function getLowStockCount(int $shopId): int
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

    public function getPendingPurchases(int $shopId): int
    {
        return PurchaseItem::where('status', 'pending')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->count();
    }

    public function getAllTimeSales(int $shopId): int
    {
        return (int) (Sale::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))->sum('grand_total') ?? 0);
    }

    public function getSalesChart(int $shopId, int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        return Sale::where('created_at', '>=', $startDate)
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(grand_total) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
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
            ->select('products.name as product_name', 'products.image as product_image', DB::raw('SUM(sale_items.quantity) as total_quantity'))
            ->groupBy('products.name', 'products.image')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getRecentSales(int $shopId, int $limit = 5): Collection
    {
        return Sale::with('saleItems')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
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
            ->select('products.name as product_name', 'products.image as product_image', DB::raw('SUM(sale_items.quantity) as total_quantity'))
            ->groupBy('products.name', 'products.image')
            ->orderBy('total_quantity')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
