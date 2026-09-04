<?php

namespace App\Contracts;

use App\Models\Product;

interface StockCalculatorInterface
{
    /**
     * Calculate available stock for a product based on its type.
     */
    public function calculateStock(Product $product): int;

    /**
     * Check if the requested quantity is available.
     */
    public function isAvailable(Product $product, int $quantity): bool;

    /**
     * Deduct stock from the product (may affect related records).
     */
    public function deduct(Product $product, int $quantity): void;

    /**
     * Restore stock to the product (may affect related records).
     */
    public function restore(Product $product, int $quantity): void;
}
