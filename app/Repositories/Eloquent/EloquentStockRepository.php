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

    public function deduct(int $productId, int $quantity, ?string $size = null, ?string $color = null): void
    {
        $product = $this->product->findOrFail($productId);

        $calculator = $product->stockCalculator;
        $available = $product->getAvailableStock();

        if ($calculator instanceof \App\Calculators\VariantStockCalculator && ($size !== null || $color !== null)) {
            if (!$calculator->isVariantAvailable($product, $quantity, $size, $color)) {
                $variantQty = 0;
                foreach ($product->variants ?? [] as $v) {
                    $matchSize = ($size === null || $size === '' || ($v['size'] ?? '') === $size);
                    $matchColor = ($color === null || $color === '' || ($v['color'] ?? '') === $color);
                    if ($matchSize && $matchColor) {
                        $variantQty = $v['quantity'] ?? 0;
                        break;
                    }
                }
                throw new \App\Exceptions\InsufficientStockException(
                    $product->name,
                    $quantity,
                    $variantQty
                );
            }
        } else {
            if (!$product->isStockAvailable($quantity)) {
                throw new \App\Exceptions\InsufficientStockException(
                    $product->name,
                    $quantity,
                    $available
                );
            }
        }

        $product->deductStock($quantity, $size, $color);
    }

    public function restore(int $productId, int $quantity, ?string $size = null, ?string $color = null): void
    {
        $product = $this->product->find($productId);
        if (!$product) return;
        $product->restoreStock($quantity, $size, $color);
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
