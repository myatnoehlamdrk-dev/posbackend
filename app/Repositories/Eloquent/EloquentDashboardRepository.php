<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Eloquent\Concerns\DashboardTableQueries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentDashboardRepository implements DashboardRepositoryInterface
{
    use DashboardTableQueries;

    /**
     * Shared by the low-stock count and the low-stock table so the number on the
     * card and the rows behind it can never disagree.
     */
    private const LOW_STOCK_THRESHOLD = 5;

    /**
     * Upper bound of the "mid" stock tier. Above this a product is "high"
     * stock. Low/out reuse `LOW_STOCK_THRESHOLD` so the tier counts agree with
     * the existing Low Stock card.
     */
    private const MID_STOCK_THRESHOLD = 20;

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

    public function getTotalProducts(int $shopId, int $userId): int
    {
        return $this->scopedProductsQuery($shopId, $userId)->count();
    }

    public function getInStockCount(int $shopId, int $userId): int
    {
        return $this->scopedProducts($shopId, $userId)
            ->filter(fn (Product $product) => $product->getAvailableStock() > 0)
            ->count();
    }

    public function getLowStockCount(int $shopId, int $userId): int
    {
        return $this->scopedProducts($shopId, $userId)
            ->filter(fn (Product $product) => $product->getAvailableStock() > 0 && $product->getAvailableStock() <= self::LOW_STOCK_THRESHOLD)
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

    public function getCategoryDistribution(int $shopId, int $userId): array
    {
        return DB::table('categories')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->leftJoin('packages', 'packages.category_id', '=', 'categories.id')
            ->leftJoin('products', 'products.package_id', '=', 'packages.id')
            ->where(function ($q) use ($shopId, $userId) {
                $q->where('inventories.shop_id', $shopId)
                  ->orWhere('categories.user_id', $userId);
            })
            ->select(
                'categories.name as category_name',
                DB::raw('COUNT(DISTINCT CASE WHEN products.active = 1 THEN products.id END) as product_count')
            )
            ->groupBy('categories.name')
            ->orderByDesc('product_count')
            ->get()
            ->toArray();
    }

    public function getCategoryQuantitySold(int $shopId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        return DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('inventories.shop_id', $shopId)
            ->where('sale_items.created_at', '>=', $startDate)
            ->select(
                'categories.name as category_name',
                DB::raw('COALESCE(SUM(sale_items.quantity), 0) as total_quantity')
            )
            ->groupBy('categories.name')
            ->orderByDesc('total_quantity')
            ->get()
            ->toArray();
    }

    public function getNoBoughtProducts(int $shopId, int $userId, int $limit = 3): array
    {
        return DB::table('products')
            ->where('products.active', true)
            ->where($this->productScope($shopId, $userId))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sale_items')
                    ->whereColumn('sale_items.product_id', 'products.id');
            })
            ->select(
                'products.name as product_name',
                'products.image as product_image',
                DB::raw('0 as total_quantity')
            )
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTotalCategories(int $shopId, int $userId): int
    {
        return DB::table('categories')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('categories.active', true)
            ->where(function ($q) use ($shopId, $userId) {
                $q->where('inventories.shop_id', $shopId)
                  ->orWhere('categories.user_id', $userId);
            })
            ->count();
    }

    public function getTotalPackages(int $shopId, int $userId): int
    {
        return DB::table('packages')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('packages.active', true)
            ->where(function ($q) use ($shopId, $userId) {
                $q->where('inventories.shop_id', $shopId)
                  ->orWhere('categories.user_id', $userId);
            })
            ->count();
    }

    public function getBrandCount(int $shopId, int $userId): int
    {
        return $this->scopedProductsQuery($shopId, $userId)
            ->whereNotNull('products.brand')
            ->where('products.brand', '!=', '')
            ->distinct()
            ->count('products.brand');
    }

    public function getCategorylessProductCount(int $shopId, int $userId): int
    {
        return $this->scopedProductsQuery($shopId, $userId)
            ->whereNull('products.package_id')
            ->count();
    }

    public function getStockTierCounts(int $shopId, int $userId): array
    {
        $tiers = ['high' => 0, 'mid' => 0, 'low' => 0, 'out' => 0];

        foreach ($this->scopedProducts($shopId, $userId) as $product) {
            $stock = $product->getAvailableStock();
            if ($stock <= 0) {
                $tiers['out']++;
            } elseif ($stock <= self::LOW_STOCK_THRESHOLD) {
                $tiers['low']++;
            } elseif ($stock <= self::MID_STOCK_THRESHOLD) {
                $tiers['mid']++;
            } else {
                $tiers['high']++;
            }
        }

        return $tiers;
    }

    /**
     * "In Cart" counts the open (draft) orders a shop's users have in their
     * cart — one per cart card, matching what the Add to Cart screen lists.
     */
    public function getInCartCount(int $shopId): int
    {
        return Order::where('status', 'draft')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->count();
    }

    public function getSalesCount(int $shopId): int
    {
        return Sale::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))->count();
    }

    public function getRecentSalesAverages(int $shopId, int $limit = 10): array
    {
        $sales = Sale::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->withCount('saleItems')
            ->latest()
            ->limit($limit)
            ->get();

        if ($sales->isEmpty()) {
            return ['avg_total' => 0, 'avg_products' => 0];
        }

        return [
            'avg_total' => (int) round((float) $sales->avg('grand_total')),
            'avg_products' => (int) round((float) $sales->avg('sale_items_count')),
        ];
    }

    public function getSalesYears(int $shopId): array
    {
        return Sale::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->whereNotNull('created_at')
            ->select(DB::raw('YEAR(created_at) as year'))
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int)$y)
            ->all();
    }

    public function getMonthlySales(int $shopId, int $year): array
    {
        $totals = Sale::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->whereYear('created_at', $year)
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COALESCE(SUM(grand_total), 0) as total')
            )
            ->groupBy('month')
            ->pluck('total', 'month')
            ->map(fn ($v) => (int)$v)
            ->all();

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = [
                'month' => $m,
                'total' => $totals[$m] ?? 0,
            ];
        }

        return $months;
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

    /**
     * The products that "belong" to this shop + user and therefore show up on
     * this shop's dashboard. Each entry is one way a product can land here:
     *
     *   - it sits in this shop's category/inventory chain,
     *   - it shares a package with a product that is,
     *   - it lives in a category the user owns (any shop),
     *   - it was created by one of this shop's users (which is how quick-added,
     *     package-less products are attributed to their shop at all).
     *
     * Shared by the total, bought and never-bought product counts and tables so
     * that the three always add up to the same universe.
     */
    private function productScope(int $shopId, int $userId): \Closure
    {
        return function ($q) use ($shopId, $userId) {
            $q->whereExists(function ($sub) use ($shopId) {
                $sub->select(DB::raw(1))
                    ->from('packages')
                    ->join('categories', 'packages.category_id', '=', 'categories.id')
                    ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
                    ->whereColumn('packages.id', 'products.package_id')
                    ->where('inventories.shop_id', $shopId);
            })
            // OR product shares package_id with any product in this shop
            ->orWhereExists(function ($sub) use ($shopId) {
                $sub->select(DB::raw(1))
                    ->from('products as p2')
                    ->join('packages', 'packages.id', '=', 'p2.package_id')
                    ->join('categories', 'packages.category_id', '=', 'categories.id')
                    ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
                    ->where('inventories.shop_id', $shopId)
                    ->whereColumn('p2.package_id', 'products.package_id')
                    ->whereNotNull('products.package_id');
            })
            // OR product belongs to a category owned by this user (any shop)
            ->orWhereExists(function ($sub) use ($userId) {
                $sub->select(DB::raw(1))
                    ->from('packages')
                    ->join('categories', 'packages.category_id', '=', 'categories.id')
                    ->whereColumn('packages.id', 'products.package_id')
                    ->where('categories.user_id', $userId);
            })
            // OR product was created by a user of this shop. This also attributes
            // package-less products (quick add never sends a packageId) to their
            // creator's shop, so they no longer leak into every shop's dashboard.
            ->orWhereExists(function ($sub) use ($shopId) {
                $sub->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'products.created_by')
                    ->where('users.shop_id', $shopId);
            });
        };
    }

    private function scopedProductsQuery(int $shopId, int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()
            ->where('products.active', true)
            ->where($this->productScope($shopId, $userId));
    }

    private function scopedProducts(int $shopId, int $userId): Collection
    {
        return $this->scopedProductsQuery($shopId, $userId)->get();
    }
}
