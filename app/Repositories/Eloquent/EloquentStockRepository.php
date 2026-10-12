<?php

namespace App\Repositories\Eloquent;

use App\Factories\StockCalculatorFactory;
use App\Models\Product;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Support\Facades\DB;

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
        // The availability checks below are read-then-write: without a row
        // lock two concurrent checkouts can both pass the check for the last
        // unit and both decrement, overselling the product (the transaction
        // isolation does not help -- both transactions read the same
        // snapshot). The lock is held until the caller's transaction commits
        // or rolls back; nested calls reuse the caller's transaction via a
        // savepoint.
        DB::transaction(function () use ($productId, $quantity, $size, $color) {
            $product = $this->product->newQuery()
                ->lockForUpdate()
                ->findOrFail($productId);

            $calculator = $product->stockCalculator;
            $available = $product->getAvailableStock();

            if ($size !== null || $color !== null) {
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
                if (!$calculator->isAvailable($product, $quantity)) {
                    throw new \App\Exceptions\InsufficientStockException(
                        $product->name,
                        $quantity,
                        $available
                    );
                }
            }

            $calculator->deduct($product, $quantity, $size, $color);
        });
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
