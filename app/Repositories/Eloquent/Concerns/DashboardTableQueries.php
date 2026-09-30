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
 * Total, bought and never-bought products all run over the same product
 * universe (see `productScope`), exactly the set the Total Products card counts,
 * split by whether a sale line ever touched the product. That is what makes
 * `total = bought + no-bought` hold even for quick-added products that have no
 * package or category, which the earlier category-chain joins silently dropped.
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
    ): LengthAwarePaginator {
        $query = $this->scopedProductsQuery($shopId, $userId)
            ->with('package.category', 'createdByUser')
            ->orderByDesc('products.created_at');

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
    ): LengthAwarePaginator {
        $products = $this->scopedProducts($shopId, $userId)
            ->load('package.category')
            ->filter(function (Product $product) use ($lowOnly) {
                $stock = $product->getAvailableStock();

                return $stock > 0 && (!$lowOnly || $stock <= self::LOW_STOCK_THRESHOLD);
            })
            ->values();

        $rows = $products->flatMap(fn (Product $product) => $this->stockRowsFor($product))->values();

        return $this->paginatorFor($rows, $page, $perPage);
    }

    /**
     * @param  int|null  $month  Filter to one calendar month, as `YYYY-MM`. Null
     *                           means all time. The month is resolved to a half
     *                           open range so that the last day of the month
     *                           is included without depending on whether
     *                           `created_at` has a time component.
     */
    public function paginateSalesTable(
        int $shopId,
        int $page = 1,
        int $perPage = 15,
        ?string $month = null,
    ): LengthAwarePaginator {
        $query = Sale::with('saleItems')
            ->whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->orderByDesc('sales.created_at');

        $this->applyMonthFilter($query, $month);

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
     * $direction 'desc' is most bought, 'asc' is least bought. Membership is
     * "ever has a sale line", which is the half of the product universe that
     * the Total Products card counts, so that bought + no-bought always add up
     * to total. The dashboard cards that promote this table still fold a 30-day
     * window into their ordering; the table itself is all-time to keep the
     * three totals reconcilable in the "View all" screens.
     */
    public function paginateBoughtProductsTable(
        int $shopId,
        int $userId,
        int $page = 1,
        int $perPage = 15,
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $order = $direction === 'asc' ? 'asc' : 'desc';

        $query = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('products.active', true)
            ->where($this->productScope($shopId, $userId))
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
        int $userId,
        int $page = 1,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = DB::table('products')
            ->leftJoin('users', 'products.created_by', '=', 'users.id')
            ->where('products.active', true)
            ->where($this->productScope($shopId, $userId))
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

    /**
     * Restricts a query to a single calendar month given as `YYYY-MM`.
     *
     * Anything unparseable is ignored rather than throwing, because the value
     * arrives straight from the query string. `>=` the first instant of the
     * month and `<` the first instant of the next one keeps the filter index
     * friendly and includes the whole final day whatever its time component is.
     */
    private function applyMonthFilter($query, ?string $month): void
    {
        if ($month === null || ! preg_match('/^(\d{4})-(\d{2})$/', trim($month), $matches)) {
            return;
        }

        $start = Carbon::create((int) $matches[1], (int) $matches[2], 1)->startOfMonth();
        $end = $start->copy()->addMonthNoOverflow();

        $query->where('created_at', '>=', $start)->where('created_at', '<', $end);
    }
}
