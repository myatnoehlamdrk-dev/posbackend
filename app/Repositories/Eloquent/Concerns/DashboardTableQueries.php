<?php

namespace App\Repositories\Eloquent\Concerns;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Row-level data behind the "View all" tables on the dashboard.
 *
 * The dashboard cards only need counts, so the queries above them can aggregate
 * hard. Once a card is tapped the user wants the actual rows, and the columns
 * they asked for do not line up with any single existing aggregate:
 *
 *   - a product's stock is not `products.stock`. It is computed by
 *     StockCalculatorFactory (simple / bundle / variant), so it has to be read
 *     off the model rather than selected as a column.
 *   - "which time" and "who created" need `created_at` / `created_by` joined to
 *     `users`, which none of the card queries touch.
 *   - most/least bought needs a unit price and a revenue figure that the card
 *     query throws away.
 *   - no-bought needs a price, and `products` has no price column at all; the
 *     only price on a product lives in its `variants` JSON.
 *
 * Every query here is scoped exactly like the card it backs, so the row count
 * on the table agrees with the number on the card. Where a card filters in PHP
 * (in-stock, low-stock) the table does the same, because the available stock of
 * a bundle or variant product cannot be expressed in SQL here.
 */
trait DashboardTableQueries
{
    public function paginateProductsTable(
        int $shopId,
        int $userId,
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        $query = $this->scopedProductsQuery($shopId, $userId)
            ->with('package.category', 'createdByUser')
            ->orderByDesc('products.created_at');

        $this->applyNameSearch($query, $search);

        $paginator = $query->paginate($perPage, ['products.*'], 'page', $page);

        $paginator->getCollection()->transform(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => $product->getAvailableStock(),
                'category' => $product->package?->category?->name ?? '',
                'package' => $product->package?->name ?? '',
                'created_by' => $product->createdByUser?->name ?? '',
                'created_at' => $product->created_at?->toDateTimeString(),
            ];
        });

        return $paginator;
    }

    /**
     * One row per variant for variant products, one row for simple and bundle
     * products. Filtering happens per product, before the split, so that the
     * table still answers "which products are low on stock" rather than
     * "which variants happen to be under the line".
     */
    public function paginateStockTable(
        int $shopId,
        int $userId,
        int $page = 1,
        int $perPage = 15,
        bool $lowOnly = false,
        ?string $search = null,
    ): LengthAwarePaginator {
        $products = $this->scopedProducts($shopId, $userId)
            ->load('package.category')
            ->filter(function (Product $product) use ($lowOnly) {
                $stock = $product->getAvailableStock();

                return $stock > 0 && (!$lowOnly || $stock <= self::LOW_STOCK_THRESHOLD);
            })
            ->filter(fn (Product $product) => $this->matchesName($product->name, $search))
            ->values();

        $rows = $products->flatMap(fn (Product $product) => $this->stockRowsFor($product))->values();

        return $this->paginatorFor($rows, $page, $perPage);
    }

    public function paginateSalesTable(
        int $shopId,
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
        ?string $from = null,
        ?string $to = null,
    ): LengthAwarePaginator {
        $query = Sale::with('saleItems')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->orderByDesc('sales.created_at');

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('voucher_no', 'like', $like)
                    ->orWhere('product_name', 'like', $like)
                    ->orWhere('user_name', 'like', $like)
                    ->orWhere('customer_name', 'like', $like);
            });
        }

        if ($from !== null && $from !== '') {
            $query->whereDate('sales.created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to !== null && $to !== '') {
            $query->whereDate('sales.created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function (Sale $sale) {
            $items = $sale->saleItems;
            $summary = $items
                ->map(fn ($item) => trim($item->product_name . ' x' . $item->quantity))
                ->filter()
                ->implode(', ');

            return [
                'id' => $sale->id,
                'voucher_no' => $sale->voucher_no ?? '',
                'date' => $sale->created_at?->toDateString(),
                'time' => $sale->created_at?->format('H:i'),
                'products' => $summary,
                'product_count' => (int) $items->sum('quantity'),
                'line_count' => $items->count(),
                'price' => (int) $sale->grand_total,
                'pay_method' => $sale->pay_method ?? '',
                'user' => $sale->user_name ?: ($sale->user?->name ?? ''),
            ];
        });

        return $paginator;
    }

    /**
     * $direction 'desc' is most bought, 'asc' is least bought. Both cards read
     * the same 30-day window, so both tables do too unless days says otherwise.
     */
    public function paginateBoughtProductsTable(
        int $shopId,
        int $page = 1,
        int $perPage = 15,
        string $direction = 'desc',
        int $days = 30,
        ?string $search = null,
    ): LengthAwarePaginator {
        $order = $direction === 'asc' ? 'asc' : 'desc';

        $query = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->where('inventories.shop_id', $shopId)
            ->where('sale_items.created_at', '>=', Carbon::now()->subDays($days))
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('COALESCE(SUM(sale_items.quantity), 0) as bought_stock'),
                DB::raw('COALESCE(SUM(sale_items.subtotal), 0) as total_price'),
                // Weighted average, not AVG(unit_price): a product sold once at
                // 100 and once at 10 has an average of 55, not 50, and the
                // quantity is what the user is looking at anyway.
                DB::raw(
                    'CASE WHEN SUM(sale_items.quantity) > 0'
                    . ' THEN ROUND(COALESCE(SUM(sale_items.subtotal), 0) / SUM(sale_items.quantity))'
                    . ' ELSE 0 END as unit_price'
                ),
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('bought_stock', $order);

        $this->applyNameSearch($query, $search, 'products.name');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(fn ($row) => [
            'id' => $row->product_id,
            'name' => $row->product_name,
            'bought_stock' => (int) $row->bought_stock,
            'unit_price' => (int) $row->unit_price,
            'total_price' => (int) $row->total_price,
        ]);

        return $paginator;
    }

    /**
     * `products` has no price column, so the price shown is the first variant's
     * price, which is the same figure the products list shows for a product.
     */
    public function paginateNoBoughtProductsTable(
        int $shopId,
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        $query = DB::table('products')
            ->join('packages', 'products.package_id', '=', 'packages.id')
            ->join('categories', 'packages.category_id', '=', 'categories.id')
            ->join('inventories', 'categories.inventory_id', '=', 'inventories.id')
            ->leftJoin('users', 'products.created_by', '=', 'users.id')
            ->where('products.active', true)
            ->where('inventories.shop_id', $shopId)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sale_items')
                    ->whereColumn('sale_items.product_id', 'products.id');
            })
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.variants as product_variants',
                'products.created_at as product_created_at',
                'users.name as creator_name',
            )
            ->orderByDesc('products.created_at');

        $this->applyNameSearch($query, $search, 'products.name');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(fn ($row) => [
            'id' => $row->product_id,
            'name' => $row->product_name,
            'price' => $this->firstVariantPrice($row->product_variants),
            'creator_name' => $row->creator_name ?? '',
            'created_at' => $row->product_created_at,
        ]);

        return $paginator;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    private function paginatorFor($rows, int $page, int $perPage): LengthAwarePaginator
    {
        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->fullUrl()],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stockRowsFor(Product $product): array
    {
        $category = $product->package?->category?->name ?? '';
        $variants = $product->variants ?? [];

        if ($variants === []) {
            return [[
                'id' => $product->id,
                'name' => $product->name,
                'size' => (string) $product->size,
                'color' => (string) $product->color,
                'stock' => $product->getAvailableStock(),
                'category' => $category,
            ]];
        }

        $rows = [];
        foreach ($variants as $variant) {
            $rows[] = [
                'id' => $product->id,
                'name' => $product->name,
                'size' => (string) ($variant['size'] ?? ''),
                'color' => (string) ($variant['color'] ?? ''),
                'stock' => (int) ($variant['quantity'] ?? 0),
                'category' => $category,
            ];
        }

        return $rows;
    }

    private function firstVariantPrice(mixed $variants): int
    {
        $decoded = is_string($variants) ? json_decode($variants, true) : $variants;

        if (!is_array($decoded) || $decoded === []) {
            return 0;
        }

        return (int) round((float) ($decoded[0]['price'] ?? 0));
    }

    private function applyNameSearch($query, ?string $search, string $column = 'products.name'): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $query->where($column, 'like', '%' . $search . '%');
    }

    private function matchesName(string $name, ?string $search): bool
    {
        if ($search === null || $search === '') {
            return true;
        }

        return str_contains(mb_strtolower($name), mb_strtolower($search));
    }
}
