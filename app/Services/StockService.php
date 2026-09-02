<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;

class StockService
{
    public function add(int $productId, int $quantity): bool
    {
        $product = Product::find($productId);

        if (!$product) {
            return false;
        }

        $product->increment('stock', $quantity);

        return true;
    }

    public function deduct(int $productId, int $quantity): void
    {
        $product = Product::find($productId);

        if (!$product) {
            throw new InsufficientStockException('Unknown product', $quantity, 0);
        }

        if ($product->stock < $quantity) {
            throw new InsufficientStockException(
                $product->name,
                $quantity,
                $product->stock,
            );
        }

        $product->decrement('stock', $quantity);
    }

    public function restore(int $productId, int $quantity): void
    {
        $product = Product::find($productId);

        if (!$product) {
            return;
        }

        $product->increment('stock', $quantity);
    }

    public function getStock(int $productId): int
    {
        $product = Product::find($productId);

        return $product?->stock ?? 0;
    }

    public function isAvailable(int $productId, int $quantity): bool
    {
        return $this->getStock($productId) >= $quantity;
    }
}
