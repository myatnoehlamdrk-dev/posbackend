<?php

namespace App\Calculators;

use App\Contracts\StockCalculatorInterface;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Bundle product: stock is the minimum of all component products' stocks.
 * A bundle can only be sold if ALL components are available.
 */
class BundleStockCalculator implements StockCalculatorInterface
{
    public function calculateStock(Product $product): int
    {
        $components = $product->bundle_components ?? [];

        if (empty($components)) {
            return 0;
        }

        // One query for the whole bundle. This runs for every bundle row that
        // gets serialized — lists, search, category-with-products — so the
        // per-component Product::find it replaces was (components x rows)
        // queries over the TiDB gateway.
        $products = Product::query()
            ->whereIn('id', $this->componentIds($components))
            ->get(['id', 'stock'])
            ->keyBy('id');

        $minStock = PHP_INT_MAX;

        foreach ($components as $component) {
            $componentProduct = $products->get((int) $component['product_id']);
            if (!$componentProduct) {
                throw new \RuntimeException(
                    "Bundle component product #{$component['product_id']} not found for bundle #{$product->id}"
                );
            }

            $requiredQty = $component['quantity'] ?? 1;
            $availableForComponent = intdiv($componentProduct->stock, $requiredQty);

            $minStock = min($minStock, $availableForComponent);
        }

        return $minStock === PHP_INT_MAX ? 0 : $minStock;
    }

    public function isAvailable(Product $product, int $quantity): bool
    {
        return $this->calculateStock($product) >= $quantity;
    }

    public function deduct(Product $product, int $quantity, ?string $size = null, ?string $color = null): void
    {
        $components = $product->bundle_components ?? [];

        if (empty($components)) {
            return;
        }

        $componentIds = $this->componentIds($components);

        // Lock every component in ascending id order so two bundles that
        // share components can never deadlock against each other (or against
        // a simple-product sale, which locks its single row). The check and
        // the writes happen under the same lock: without it two concurrent
        // bundle sales can both pass an unlocked check for the last unit.
        DB::transaction(function () use ($components, $componentIds, $quantity) {
            $products = Product::query()
                ->whereIn('id', $componentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Verify every component covers its share before deducting any,
            // so an insufficient component cannot leave a partial deduction.
            foreach ($components as $component) {
                $componentProduct = $products->get((int) $component['product_id']);
                if (!$componentProduct) {
                    throw new \RuntimeException(
                        "Cannot deduct bundle component: Product #{$component['product_id']} not found"
                    );
                }

                $deductQty = ($component['quantity'] ?? 1) * $quantity;
                $available = $componentProduct->stock ?? 0;
                if ($available < $deductQty) {
                    throw new InsufficientStockException(
                        $componentProduct->name,
                        (int) $deductQty,
                        (int) $available
                    );
                }
            }

            foreach ($components as $component) {
                $componentProduct = $products->get((int) $component['product_id']);
                $deductQty = ($component['quantity'] ?? 1) * $quantity;
                $componentProduct->decrement('stock', $deductQty);
            }
        });
    }

    public function restore(Product $product, int $quantity, ?string $size = null, ?string $color = null): void
    {
        $components = $product->bundle_components ?? [];

        if (empty($components)) {
            return;
        }

        // Ascending id order matches deduct()'s lock order: restores take
        // implicit write locks one row at a time, and acquiring them in the
        // same order every time keeps restore and deduct from deadlocking.
        $componentIds = $this->componentIds($components);
        $products = Product::query()
            ->whereIn('id', $componentIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        foreach ($components as $component) {
            $componentProduct = $products->get((int) $component['product_id']);
            if (!$componentProduct) {
                throw new \RuntimeException(
                    "Cannot restore bundle component: Product #{$component['product_id']} not found"
                );
            }

            $restoreQty = ($component['quantity'] ?? 1) * $quantity;
            $componentProduct->increment('stock', $restoreQty);
        }
    }

    /**
     * Unique component product ids in ascending order -- the lock order for
     * every bundle stock operation.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, int>
     */
    private function componentIds(array $components): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (array $component): int => (int) $component['product_id'],
            $components,
        )));
        sort($ids);

        return $ids;
    }

    public function isVariantAvailable(Product $product, int $quantity, ?string $size = null, ?string $color = null): bool
    {
        return $this->isAvailable($product, $quantity);
    }
}
