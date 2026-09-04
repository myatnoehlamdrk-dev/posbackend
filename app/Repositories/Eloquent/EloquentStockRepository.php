<?php

namespace App\Repositories\Eloquent;

use App\Factories\StockCalculatorFactory;
use App\Models\Product;
use App\Repositories\Contracts\StockRepositoryInterface;

class EloquentStockRepository implements StockRepositoryInterface
{
    public function __construct(
        protected Product $product,
    ) {}

    public function add(int $productId, int $quantity): bool
    {
        $product = $this->product->findOrFail($productId);
        $product->restoreStock($quantity);
        return true;
    }

    public function deduct(int $productId, int $quantity): void
    {
        $product = $this->product->findOrFail($productId);

        if (!$product->isStockAvailable($quantity)) {
            throw new \App\Exceptions\InsufficientStockException(
                $product->name,
                $quantity,
                $product->getAvailableStock()
            );
        }

        $product->deductStock($quantity);
    }

    public function restore(int $productId, int $quantity): void
    {
        $product = $this->product->findOrFail($productId);
        $product->restoreStock($quantity);
    }

    public function getStock(int $productId): int
    {
        $product = $this->product->findOrFail($productId);
        return $product->getAvailableStock();
    }

    public function isAvailable(int $productId, int $quantity): bool
    {
        $product = $this->product->findOrFail($productId);
        return $product->isStockAvailable($quantity);
    }
}
