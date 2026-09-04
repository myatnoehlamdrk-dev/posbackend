<?php

namespace App\Calculators;

use App\Contracts\StockCalculatorInterface;
use App\Models\Product;

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

        $minStock = PHP_INT_MAX;

        foreach ($components as $component) {
            $componentProduct = Product::find($component['product_id']);
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

    public function deduct(Product $product, int $quantity): void
    {
        $components = $product->bundle_components ?? [];

        foreach ($components as $component) {
            $componentProduct = Product::find($component['product_id']);
            if (!$componentProduct) {
                throw new \RuntimeException(
                    "Cannot deduct bundle component: Product #{$component['product_id']} not found"
                );
            }

            $deductQty = ($component['quantity'] ?? 1) * $quantity;
            $componentProduct->decrement('stock', $deductQty);
        }
    }

    public function restore(Product $product, int $quantity): void
    {
        $components = $product->bundle_components ?? [];

        foreach ($components as $component) {
            $componentProduct = Product::find($component['product_id']);
            if (!$componentProduct) {
                throw new \RuntimeException(
                    "Cannot restore bundle component: Product #{$component['product_id']} not found"
                );
            }

            $restoreQty = ($component['quantity'] ?? 1) * $quantity;
            $componentProduct->increment('stock', $restoreQty);
        }
    }
}
