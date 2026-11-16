<?php

namespace App\Services;

use App\Models\OperationFee;
use App\Models\OperationFeeSetting;
use App\Models\Product;
use App\Repositories\Eloquent\Concerns\ProductShopScope;
use Illuminate\Support\Facades\DB;

class OperationFeeService
{
    public function listForShop(int $shopId): array
    {
        return OperationFee::where('shop_id', $shopId)
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    public function create(int $shopId, array $data): OperationFee
    {
        return OperationFee::create([
            'shop_id' => $shopId,
            'name' => $data['name'],
            'category' => $data['category'] ?? 'other',
            'calculation' => $data['calculation'] ?? 'fixedPerMonth',
            'rate' => $data['rate'] ?? 0,
            'is_active' => $data['isActive'] ?? true,
            'icon' => $data['icon'] ?? 'other',
            'tint' => $data['tint'] ?? 'gray',
        ]);
    }

    public function update(OperationFee $fee, array $data): OperationFee
    {
        $fee->update(array_filter([
            'name' => $data['name'] ?? null,
            'category' => $data['category'] ?? null,
            'calculation' => $data['calculation'] ?? null,
            'rate' => array_key_exists('rate', $data) ? $data['rate'] : null,
            'is_active' => array_key_exists('isActive', $data) ? $data['isActive'] : null,
            'icon' => $data['icon'] ?? null,
            'tint' => $data['tint'] ?? null,
        ], fn ($v) => $v !== null));

        return $fee->fresh();
    }

    public function delete(OperationFee $fee): void
    {
        $fee->delete();
    }

    /**
     * The shop-wide rules evaluated for one month.
     *
     * Rules are not stored per month; their monthly amount is computed from
     * the month's revenue and transaction count instead.
     *
     * @return array{fees: array<int, array<string, mixed>>, total: float}
     */
    public function monthlyBreakdown(
        int $shopId,
        int $year,
        int $month,
        float $revenue,
        int $orders,
    ): array {
        $fees = OperationFee::where('shop_id', $shopId)
            ->orderBy('id')
            ->get()
            ->map(fn (OperationFee $fee) => [
                'name' => $fee->name,
                'category' => $fee->category,
                'calculation' => $fee->calculation,
                'rate' => (float) $fee->rate,
                'is_active' => (bool) $fee->is_active,
                'monthly_cost' => round($this->monthlyCost($fee, $year, $month, $revenue, $orders), 2),
            ])
            ->all();

        return [
            'fees' => $fees,
            'total' => round(array_sum(array_column($fees, 'monthly_cost')), 2),
        ];
    }

    /**
     * How the month's operation fees land on real products.
     *
     * Each product's share is its revenue, units or gross profit divided by
     * the same total across the month's products, depending on
     * [$allocationMethod]. When [$productId] is given that product is
     * previewed; otherwise the month's highest-revenue product is used, so
     * changing the method moves the allocated fee and margin without swapping
     * the product underneath the user.
     *
     * @return array<string, mixed>
     */
    public function productImpact(
        int $shopId,
        int $year,
        int $month,
        string $allocationMethod,
        float $revenue,
        int $orders,
        ?int $productId = null,
    ): array {
        $totalFee = (float) $this->monthlyBreakdown(
            $shopId,
            $year,
            $month,
            $revenue,
            $orders,
        )['total'];

        $rows = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->where('users.shop_id', $shopId)
            ->whereYear('sales.created_at', $year)
            ->whereMonth('sales.created_at', $month)
            ->select(
                'sale_items.product_id',
                'sale_items.product_name',
                'sale_items.quantity',
                'sale_items.unit_price',
                'sale_items.subtotal',
                'sale_items.size',
                'sale_items.color',
                'products.sku',
                'products.variants',
            )
            ->get();

        $products = [];

        foreach ($rows as $row) {
            $key = $row->product_id !== null
                ? 'id:'.$row->product_id
                : 'name:'.$row->product_name;

            if (! isset($products[$key])) {
                $products[$key] = [
                    'id' => $row->product_id !== null ? (string) $row->product_id : null,
                    'name' => $row->product_name,
                    'sku' => $row->sku ?? '',
                    'units' => 0,
                    'revenue' => 0.0,
                    'cost' => 0.0,
                ];
            }

            $quantity = (int) $row->quantity;
            $lineRevenue = (float) $row->subtotal;

            // Older rows can carry a zero subtotal while still holding a valid
            // unit price, so fall back to the line arithmetic rather than
            // reporting the product as earning nothing.
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) $row->unit_price * $quantity;
            }

            $products[$key]['units'] += $quantity;
            $products[$key]['revenue'] += $lineRevenue;
            $products[$key]['cost'] += $this->variantUnitCost(
                $row->variants,
                $row->size,
                $row->color,
            ) * $quantity;
        }

        $totalRevenue = 0.0;
        $totalUnits = 0;
        $totalGrossProfit = 0.0;

        foreach ($products as $index => $product) {
            $grossProfit = $product['revenue'] - $product['cost'];
            $products[$index]['grossProfit'] = $grossProfit;

            $totalRevenue += $product['revenue'];
            $totalUnits += $product['units'];
            $totalGrossProfit += $grossProfit;
        }

        $denominator = match ($allocationMethod) {
            'unitsSold' => (float) $totalUnits,
            'grossProfit' => max(0.0, $totalGrossProfit),
            default => $totalRevenue,
        };

        $selected = null;
        $hasSales = false;

        if ($productId !== null) {
            $selected = $products['id:'.$productId] ?? null;
            $hasSales = $selected !== null;

            if ($selected === null) {
                // Any product can be previewed, including one that sold
                // nothing this month. Its fee share is zero, so the preview
                // falls back to the variant selling price and buying cost.
                $selected = $this->productWithoutSales($shopId, $productId);
            }
        } elseif ($products !== []) {
            usort(
                $products,
                fn (array $a, array $b) => $b['revenue'] <=> $a['revenue'],
            );

            $selected = $products[0];
            $hasSales = true;
        }

        $preview = null;

        if ($selected !== null) {
            $units = max(0, (int) $selected['units']);
            $metric = match ($allocationMethod) {
                'unitsSold' => (float) $units,
                'grossProfit' => max(0.0, (float) ($selected['grossProfit'] ?? 0.0)),
                default => (float) $selected['revenue'],
            };
            $share = $denominator > 0 ? $metric / $denominator : 0.0;
            $allocatedFee = $totalFee * $share;

            // Divide by at least one so a malformed historical row cannot turn
            // the per-unit figures into a division by zero.
            $divisor = max(1, $units);
            $unitSellingPrice = $hasSales
                ? (float) $selected['revenue'] / $divisor
                : (float) ($selected['unitSellingPrice'] ?? 0.0);
            $unitCost = $hasSales
                ? (float) $selected['cost'] / $divisor
                : (float) ($selected['unitCost'] ?? 0.0);
            $unitAllocatedFee = $hasSales ? $allocatedFee / $divisor : 0.0;
            $trueUnitCost = $unitCost + $unitAllocatedFee;

            // The month's fee is fixed across the three methods; only the
            // product's share of it (and therefore the preview's basis) moves.
            // Both are returned so the card can say so explicitly.
            $grossProfit = (float) ($selected['grossProfit'] ?? 0.0);

            $preview = [
                'id' => $selected['id'],
                'name' => $selected['name'],
                'sku' => $selected['sku'],
                'hasSales' => $hasSales,
                'units' => $units,
                'revenue' => round((float) $selected['revenue'], 2),
                'grossProfit' => round($grossProfit, 2),
                'allocatedFee' => round($allocatedFee, 2),
                'monthlyFee' => round($totalFee, 2),
                'unitSellingPrice' => round($unitSellingPrice, 2),
                'unitCost' => round($unitCost, 2),
                'unitAllocatedFee' => round($unitAllocatedFee, 2),
                'trueUnitCost' => round($trueUnitCost, 2),
                'trueMargin' => $unitSellingPrice > 0
                    ? round(($unitSellingPrice - $trueUnitCost) / $unitSellingPrice * 100, 1)
                    : 0.0,
            ];
        }

        return [
            'allocationMethod' => $allocationMethod,
            'year' => $year,
            'month' => $month,
            'totalFee' => round($totalFee, 2),
            'product' => $preview,
        ];
    }

    /**
     * Product figures for a product that had no sales in the selected month.
     *
     * The product is resolved through the same shop boundaries as the product
     * search, so an id from another shop cannot be previewed here.
     *
     * @return array<string, mixed>|null
     */
    private function productWithoutSales(int $shopId, int $productId): ?array
    {
        $product = Product::query()
            ->whereKey($productId)
            ->where(function ($query) use ($shopId) {
                $query->whereHas(
                    'package.category.inventory',
                    fn ($inventory) => $inventory->where('shop_id', $shopId),
                )->orWhere(
                    fn ($orphan) => ProductShopScope::applyOrphan($orphan, $shopId),
                );
            })
            ->first();

        if ($product === null) {
            return null;
        }

        [$price, $cost] = $this->variantPriceSummary($product->variants);

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'sku' => $product->sku ?? '',
            'units' => 0,
            'revenue' => 0.0,
            'cost' => 0.0,
            'unitSellingPrice' => $price,
            'unitCost' => $cost,
        ];
    }

    /**
     * Average selling price and buying cost across a product's variants.
     *
     * Used when a product sold nothing in the selected month, so there is no
     * sale-line revenue to divide into a unit selling price.
     *
     * @param  array<int, mixed>|null  $variants
     * @return array{0: float, 1: float}
     */
    private function variantPriceSummary(?array $variants): array
    {
        if (! is_array($variants)) {
            return [0.0, 0.0];
        }

        $price = 0.0;
        $cost = 0.0;
        $count = 0;

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $variantPrice = (float) ($variant['price'] ?? 0);
            $variantCost = (float) ($variant['costPrice'] ?? $variant['cost_price'] ?? 0);

            if ($variantPrice <= 0 && $variantCost <= 0) {
                continue;
            }

            $price += $variantPrice;
            $cost += $variantCost;
            $count++;
        }

        return $count > 0 ? [$price / $count, $cost / $count] : [0.0, 0.0];
    }

    /**
     * The cost of one unit from a product's variant JSON.
     *
     * Products store variant costs in camelCase, but older rows written before
     * that convention used `cost_price`, so both keys are read here.
     */
    private function variantUnitCost(
        ?string $variantsJson,
        ?string $size,
        ?string $color,
    ): float {
        if ($variantsJson === null || $variantsJson === '') {
            return 0.0;
        }

        $variants = json_decode($variantsJson, true);

        if (! is_array($variants)) {
            return 0.0;
        }

        $itemSize = $size ?? '';
        $itemColor = $color ?? '';

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            if (($variant['size'] ?? '') === $itemSize
                && ($variant['color'] ?? '') === $itemColor) {
                return (float) ($variant['costPrice'] ?? $variant['cost_price'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * Add the computed operation-fee total to each month of a sales series.
     *
     * The rules are loaded once, so building a full year costs one extra
     * query instead of one per month.
     *
     * @param  array<int, array<string, mixed>>  $months
     * @return array<int, array<string, mixed>>
     */
    public function attachMonthlyCosts(int $shopId, int $year, array $months): array
    {
        $fees = OperationFee::where('shop_id', $shopId)
            ->orderBy('id')
            ->get();

        foreach ($months as $index => $month) {
            $total = 0.0;

            foreach ($fees as $fee) {
                $total += $this->monthlyCost(
                    $fee,
                    $year,
                    (int) $month['month'],
                    (float) ($month['total'] ?? 0),
                    (int) ($month['transactions'] ?? 0),
                );
            }

            $months[$index]['operation_fee'] = round($total, 2);
        }

        return $months;
    }

    private function monthlyCost(
        OperationFee $fee,
        int $year,
        int $month,
        float $revenue,
        int $orders,
    ): float {
        if (! $fee->is_active) {
            return 0.0;
        }

        $rate = (float) $fee->rate;

        return match ($fee->calculation) {
            'fixedPerMonth' => $rate,
            'percentageOfRevenue' => $revenue * $rate / 100,
            'perOrder' => $orders * $rate,
            'oneTime' => $fee->created_at !== null
                && (int) $fee->created_at->year === $year
                && (int) $fee->created_at->month === $month
                    ? $rate
                    : 0.0,
            default => 0.0,
        };
    }

    public function getSettings(int $shopId): OperationFeeSetting
    {
        return OperationFeeSetting::firstOrCreate(
            ['shop_id' => $shopId],
            ['allocation_method' => 'revenue']
        );
    }

    public function updateSettings(int $shopId, string $allocationMethod): OperationFeeSetting
    {
        $setting = $this->getSettings($shopId);
        $setting->update(['allocation_method' => $allocationMethod]);

        return $setting->fresh();
    }
}
