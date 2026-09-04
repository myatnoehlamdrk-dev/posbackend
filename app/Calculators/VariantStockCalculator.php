<?php

namespace App\Calculators;

use App\Contracts\StockCalculatorInterface;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Variant product: stock is the sum of all variant stocks.
 * Each variant has its own quantity.
 */
class VariantStockCalculator implements StockCalculatorInterface
{
    public function calculateStock(Product $product): int
    {
        $variants = $product->variants ?? [];

        if (empty($variants)) {
            return 0;
        }

        return collect($variants)->sum('quantity') ?? 0;
    }

    public function isAvailable(Product $product, int $quantity): bool
    {
        return $this->calculateStock($product) >= $quantity;
    }

    public function deduct(Product $product, int $quantity): void
    {
        $variants = $product->variants ?? [];

        if (empty($variants)) {
            return;
        }

        // Use database lock to prevent race conditions
        DB::transaction(function () use ($product, $quantity, &$variants) {
            // Re-read fresh data under lock
            $freshProduct = Product::lockForUpdate()->find($product->id);
            $variants = $freshProduct->variants ?? [];

            $remaining = $quantity;
            $updatedVariants = [];

            foreach ($variants as $variant) {
                $variantQty = $variant['quantity'] ?? 0;

                if ($remaining <= 0) {
                    $updatedVariants[] = $variant;
                    continue;
                }

                $deductFromVariant = min($variantQty, $remaining);
                $remaining -= $deductFromVariant;

                $updatedVariants[] = array_merge($variant, [
                    'quantity' => $variantQty - $deductFromVariant,
                ]);

                if ($remaining <= 0) {
                    // Track which variant was last used for restore
                    $updatedVariants[count($updatedVariants) - 1]['_last_deducted'] = true;
                }
            }

            $freshProduct->update(['variants' => $updatedVariants]);
        });
    }

    public function restore(Product $product, int $quantity): void
    {
        $variants = $product->variants ?? [];

        if (empty($variants)) {
            $product->update(['variants' => [['quantity' => $quantity]]]);
            return;
        }

        DB::transaction(function () use ($product, $quantity, &$variants) {
            // Re-read fresh data under lock
            $freshProduct = Product::lockForUpdate()->find($product->id);
            $variants = $freshProduct->variants ?? [];

            $updatedVariants = $variants;
            $restored = false;

            // Find the variant that was last deducted (has _last_deducted flag)
            foreach ($updatedVariants as $index => &$variant) {
                if (!empty($variant['_last_deducted'])) {
                    $variant['quantity'] = ($variant['quantity'] ?? 0) + $quantity;
                    unset($variant['_last_deducted']);
                    $restored = true;
                    break;
                }
            }
            unset($variant);

            // If no flagged variant found, restore to the first variant with stock
            if (!$restored) {
                foreach ($updatedVariants as $index => &$variant) {
                    if (($variant['quantity'] ?? 0) > 0) {
                        $variant['quantity'] = ($variant['quantity'] ?? 0) + $quantity;
                        $restored = true;
                        break;
                    }
                }
                unset($variant);
            }

            // Fallback: restore to first variant
            if (!$restored && !empty($updatedVariants)) {
                $updatedVariants[0]['quantity'] = ($updatedVariants[0]['quantity'] ?? 0) + $quantity;
            }

            $freshProduct->update(['variants' => $updatedVariants]);
        });
    }
}
