<?php

namespace App\Calculators;

use App\Contracts\StockCalculatorInterface;
use App\Models\Product;

/**
 * Simple product: stock is a single number stored in the stock column.
 */
class SimpleStockCalculator implements StockCalculatorInterface
{
    public function calculateStock(Product $product): int
    {
        return $product->stock ?? 0;
    }

    public function isAvailable(Product $product, int $quantity): bool
    {
        return $this->calculateStock($product) >= $quantity;
    }

    public function deduct(Product $product, int $quantity): void
    {
        if (!$this->isAvailable($product, $quantity)) {
            throw new \App\Exceptions\InsufficientStockException(
                $product->name,
                $quantity,
                $this->calculateStock($product)
            );
        }

        $product->decrement('stock', $quantity);
    }

    public function restore(Product $product, int $quantity): void
    {
        $product->increment('stock', $quantity);
    }
}
