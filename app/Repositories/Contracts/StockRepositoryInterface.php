<?php

namespace App\Repositories\Contracts;

interface StockRepositoryInterface
{
    public function add(int $productId, int $quantity): bool;
    public function deduct(int $productId, int $quantity, ?string $size = null, ?string $color = null): void;
    public function restore(int $productId, int $quantity, ?string $size = null, ?string $color = null): void;
    public function getStock(int $productId): int;
    public function isAvailable(int $productId, int $quantity): bool;
}
