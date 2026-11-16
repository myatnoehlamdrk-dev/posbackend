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

    /**
     * How many calendar months the tiles' trend lines span. Twelve keeps the
     * sparkline readable and gives a shop a full year of context.
     */
    private const TREND_MONTHS = 12;

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

    /**
     * Products above the low-stock line. The three stock tiers are exclusive:
     * in stock (> threshold) + low (1..threshold) + out (0) add up to
     * [getTotalProducts]. Counting "stock > 0" here instead would put every
     * low-stock product in two rows at once.
     */
    public function getInStockCount(int $shopId, int $userId): int
    {
        return $this->scopedProducts($shopId, $userId)
            ->filter(fn (Product $product) => $product->getAvailableStock() > self::LOW_STOCK_THRESHOLD)
            ->count();
    }

    public function getLowStockCount(int $shopId, int $userId): int
    {
        return $this->scopedProducts($shopId, $userId)
            ->filter(fn (Product $product) => $product->getAvailableStock() > 0 && $product->getAvailableStock() <= self::LOW_STOCK_THRESHOLD)
            ->count();
    }

    public function getStockUnits(int $shopId, int $userId): int
    {
        return (int) $this->scopedProducts($shopId, $userId)
            ->sum(fn (Product $product) => $product->getAvailableStock());
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
        return $this->soldProducts($shopId, $limit, 'desc');
    }

    public function getLeastProducts(int $shopId, int $limit = 3): array
    {
        return $this->soldProducts($shopId, $limit, 'asc');
    }

    /**
     * Units sold per product in this shop over the last 30 days, in the given
     * direction: `desc` is most bought, `asc` is least bought.
     *
     * Scoped through the sale's owner (`sales.user_id → users.shop_id`) rather
     * than through the product's package → category → inventory chain. A
     * quick-added product has no package, and the inner joins to `packages`
     * dropped it entirely — which is how a shop's best-selling item could be
     * missing from "Most Bought" while its sales were counted everywhere else.
     *
     * Grouped by product id, not name: two different products that happen to
     * share a name were being folded into one row, showing one name with the
     * other's quantity. `products.active` matches the all-time table behind
     * "View all", so the card and the table never disagree about which
     * products exist.
     */
    private function soldProducts(int $shopId, int $limit, string $direction): array
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('users.shop_id', $shopId)
            ->where('products.active', true)
            ->where('sale_items.created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.image as product_image',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
            )
            ->groupBy('products.id', 'products.name', 'products.image')
            ->orderBy('total_quantity', $direction)
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
     * Package-level stock status: each active package's stock is the summed
     * available stock of the active products assigned to it. Buckets are
     * exclusive (out / low / in), so the three rows add up to the total — the
     * shapes the dashboard's stock panel draws.
     */
    public function getPackageStockTierCounts(int $shopId, int $userId): array
    {
        $packageIds = DB::table('packages')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('packages.active', true)
            ->where(function ($q) use ($shopId, $userId) {
                $q->where('inventories.shop_id', $shopId)
                  ->orWhere('categories.user_id', $userId);
            })
            ->pluck('packages.id');

        $tiers = [
            'total' => $packageIds->count(),
            'in_stock' => 0,
            'low' => 0,
            'out' => 0,
        ];

        if ($packageIds->isEmpty()) {
            return $tiers;
        }

        $stockByPackage = [];
        $products = $this->scopedProductsQuery($shopId, $userId)
            ->whereNotNull('products.package_id')
            ->whereIn('products.package_id', $packageIds)
            ->get();

        foreach ($products as $product) {
            $packageId = $product->package_id;
            $stockByPackage[$packageId] = ($stockByPackage[$packageId] ?? 0)
                + $product->getAvailableStock();
        }

        foreach ($packageIds as $packageId) {
            $stock = $stockByPackage[$packageId] ?? 0;
            if ($stock <= 0) {
                $tiers['out']++;
            } elseif ($stock <= self::LOW_STOCK_THRESHOLD) {
                $tiers['low']++;
            } else {
                $tiers['in_stock']++;
            }
        }

        return $tiers;
    }

    /**
     * Units sold across all time, scoped by the shop that rang the sale up —
     * the same scope as every other sales figure, and not dependent on the
     * product still having a category/package (quick-added products often do
     * not).
     */
    public function getAllTimeItemsSold(int $shopId): int
    {
        return (int) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->where('users.shop_id', $shopId)
            ->sum('sale_items.quantity');
    }

    /**
     * Month-by-month figures behind the dashboard's headline cards.
     *
     * The values the cards print stay all-time totals; these figures are what
     * the arrow and the trend line are drawn from. Each metric is measured per
     * calendar month for the last [self::TREND_MONTHS] months, ending with the
     * current (partial) month:
     *
     *   - `current`  = this month so far
     *   - `previous` = the whole preceding calendar month
     *   - `series`   = one point per month, oldest first, ending at `current`
     */
    public function getMonthlyComparison(int $shopId, int $userId): array
    {
        $windows = $this->monthWindows(self::TREND_MONTHS);

        $sales = $this->monthlySalesSeries($shopId, $windows);
        $units = $this->monthlyUnitsSeries($shopId, $windows);
        $averageOrder = [];

        foreach ($windows as $index => $_window) {
            $averageOrder[] = $sales['orders'][$index] > 0
                ? (int) round($sales['revenue'][$index] / $sales['orders'][$index])
                : 0;
        }

        $products = $this->scopedProducts($shopId, $userId);

        return [
            'revenue' => $this->monthFigure($sales['revenue']),
            'orders' => $this->monthFigure($sales['orders']),
            'avg_order_value' => $this->monthFigure($averageOrder),
            'items_sold' => $this->monthFigure($units),
            'products' => $this->monthFigure($this->monthlyProductSeries($products, $windows)),
            'low_stock' => $this->monthFigure($this->monthlyLowStockSeries($products, $windows)),
        ];
    }

    /**
     * One figure from its monthly series: the current month, the previous
     * month, and the series the tile draws.
     *
     * @param  list<int>  $series
     * @return array{current:int,previous:int,series:list<int>}
     */
    private function monthFigure(array $series): array
    {
        $count = count($series);

        return [
            'current' => $count > 0 ? (int) $series[$count - 1] : 0,
            'previous' => $count > 1 ? (int) $series[$count - 2] : 0,
            'series' => array_values(array_map('intval', $series)),
        ];
    }

    /**
     * Calendar-month windows, oldest first, ending with the current month.
     *
     * @return list<array{start:Carbon,end:Carbon}>
     */
    private function monthWindows(int $count): array
    {
        $currentStart = Carbon::now()->startOfMonth();
        $windows = [];

        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $start = $currentStart->copy()->subMonthsNoOverflow($offset);
            $windows[] = [
                'start' => $start,
                'end' => $start->copy()->addMonthNoOverflow(),
            ];
        }

        return $windows;
    }

    /**
     * Revenue and order counts per month.
     *
     * @return array{revenue:list<int>,orders:list<int>}
     */
    private function monthlySalesSeries(int $shopId, array $windows): array
    {
        $rows = Sale::where('created_at', '>=', $windows[0]['start'])
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as total")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $revenue = [];
        $orders = [];

        foreach ($windows as $window) {
            $row = $rows->get($window['start']->format('Y-m'));
            $revenue[] = (int) ($row->total ?? 0);
            $orders[] = (int) ($row->orders ?? 0);
        }

        return ['revenue' => $revenue, 'orders' => $orders];
    }

    /**
     * Units sold per month, scoped by the shop that rang the sale up so it
     * matches [getAllTimeItemsSold] and includes package-less products.
     *
     * @return list<int>
     */
    private function monthlyUnitsSeries(int $shopId, array $windows): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->where('users.shop_id', $shopId)
            ->where('sale_items.created_at', '>=', $windows[0]['start'])
            ->selectRaw("DATE_FORMAT(sale_items.created_at, '%Y-%m') as month, COALESCE(SUM(sale_items.quantity), 0) as total")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        return array_map(
            fn ($window) => (int) ($rows->get($window['start']->format('Y-m'))->total ?? 0),
            $windows,
        );
    }

    /**
     * How many active products the shop held at the end of each month, i.e.
     * products created before that month closed.
     *
     * @return list<int>
     */
    private function monthlyProductSeries(Collection $products, array $windows): array
    {
        return array_map(
            fn ($window) => $products
                ->filter(fn (Product $product) => $product->created_at === null || $product->created_at < $window['end'])
                ->count(),
            $windows,
        );
    }

    /**
     * The low-stock count at the end of each month. Stock history is not
     * stored, so it is reconstructed from the current stock plus what has
     * moved since:
     *
     *   stock then = stock now + sold since then - received since then
     *
     * Sales keep their own rows, so sold units are exact. Receipts are the
     * purchase items linked to a product in a later month; that is the moment
     * the stock came in, but a stock figure edited directly in the product
     * form leaves no purchase row and cannot be reconstructed. Bundle stock is
     * derived from its components and a bundle sale touches the components,
     * not the bundle's own stock column, so bundles keep their current figure.
     *
     * @return list<int>
     */
    private function monthlyLowStockSeries(Collection $products, array $windows): array
    {
        $count = count($windows);

        if ($products->isEmpty()) {
            return array_fill(0, $count, 0);
        }

        $ids = $products->pluck('id')->all();

        $sold = $this->monthlyQuantitySeries('sale_items', 'created_at', $ids, $windows);
        $received = $this->monthlyQuantitySeries('purchase_items', 'updated_at', $ids, $windows);
        $soldAfter = $this->suffixSums($sold, $ids, $count);
        $receivedAfter = $this->suffixSums($received, $ids, $count);

        $series = [];

        foreach ($windows as $index => $_window) {
            $inTier = 0;

            foreach ($products as $product) {
                $isBundle = $product->product_type === 'bundle'
                    || !empty($product->bundle_components);

                $stock = $isBundle
                    ? $product->getAvailableStock()
                    : $product->getAvailableStock()
                        + ($soldAfter[$product->id][$index] ?? 0)
                        - ($receivedAfter[$product->id][$index] ?? 0);

                if ($stock > 0 && $stock <= self::LOW_STOCK_THRESHOLD) {
                    $inTier++;
                }
            }

            $series[] = $inTier;
        }

        return $series;
    }

    /**
     * Quantity moved per product per month, indexed by the month's position in
     * [windows]. Rows outside the window are dropped.
     *
     * @param  list<int>  $productIds
     * @return array<int,array<int,int>>
     */
    private function monthlyQuantitySeries(string $table, string $dateColumn, array $productIds, array $windows): array
    {
        $indexByMonth = [];
        foreach ($windows as $index => $window) {
            $indexByMonth[$window['start']->format('Y-m')] = $index;
        }

        $rows = DB::table($table)
            ->whereIn('product_id', $productIds)
            ->where($dateColumn, '>=', $windows[0]['start'])
            ->selectRaw("product_id, DATE_FORMAT({$dateColumn}, '%Y-%m') as month, COALESCE(SUM(quantity), 0) as total")
            ->groupBy('product_id', 'month')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $index = $indexByMonth[$row->month] ?? null;
            if ($index === null) {
                continue;
            }

            $result[(int) $row->product_id][$index] = (int) $row->total;
        }

        return $result;
    }

    /**
     * For each product, the quantity moved strictly after each window — the
     * units that had not happened yet at that month's end.
     *
     * @param  array<int,array<int,int>>  $byProduct
     * @param  list<int>  $productIds
     * @return array<int,list<int>>
     */
    private function suffixSums(array $byProduct, array $productIds, int $count): array
    {
        $result = [];

        foreach ($productIds as $productId) {
            $values = $byProduct[$productId] ?? [];
            $suffix = array_fill(0, $count, 0);
            $running = 0;

            for ($index = $count - 1; $index >= 0; $index--) {
                $suffix[$index] = $running;
                $running += $values[$index] ?? 0;
            }

            $result[$productId] = $suffix;
        }

        return $result;
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

        // Units sold per month.
        $units = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->where('users.shop_id', $shopId)
            ->whereYear('sales.created_at', $year)
            ->select(DB::raw('MONTH(sales.created_at) as month'), DB::raw('COALESCE(SUM(sale_items.quantity), 0) as total'))
            ->groupBy('month')
            ->pluck('total', 'month')
            ->map(fn ($v) => (int)$v)
            ->all();

        // Transaction count per month.
        $txns = Sale::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->whereYear('created_at', $year)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('month')
            ->pluck('cnt', 'month')
            ->map(fn ($v) => (int)$v)
            ->all();

        // Purchase cost (outflow) per month.
        $purchases = DB::table('purchase_items')
            ->where('shop_id', $shopId)
            ->whereYear('created_at', $year)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('COALESCE(SUM(total_price), 0) as total'))
            ->groupBy('month')
            ->pluck('total', 'month')
            ->map(fn ($v) => (int)$v)
            ->all();

        // COGS per month: variant cost_price * quantity for each sale item.
        // Match variant by size+color. No product-level fallback.
        $saleItems = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('users.shop_id', $shopId)
            ->whereYear('sales.created_at', $year)
            ->select(
                'sale_items.quantity',
                'sale_items.size',
                'sale_items.color',
                DB::raw('MONTH(sales.created_at) as month'),
                'products.variants'
            )
            ->get();

        $cogs = [];
        foreach ($saleItems as $item) {
            $month = (int) $item->month;
            $variants = json_decode($item->variants, true) ?? [];
            $variantCost = 0;

            foreach ($variants as $v) {
                $sizeMatch = ($v['size'] ?? null) === $item->size || ($item->size === null && empty($v['size']));
                $colorMatch = ($v['color'] ?? null) === $item->color || ($item->color === null && empty($v['color']));
                if ($sizeMatch && $colorMatch && isset($v['cost_price'])) {
                    $variantCost = (float) $v['cost_price'];
                    break;
                }
            }

            if (!isset($cogs[$month])) {
                $cogs[$month] = 0;
            }
            $cogs[$month] += $item->quantity * $variantCost;
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = [
                'month' => $m,
                'total' => $totals[$m] ?? 0,
                'cogs'  => (int) ($cogs[$m] ?? 0),
                'units' => $units[$m] ?? 0,
                'transactions' => $txns[$m] ?? 0,
                'purchases' => $purchases[$m] ?? 0,
            ];
        }

        return $months;
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
