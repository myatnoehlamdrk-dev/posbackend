<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        private readonly string $productName,
        private readonly int $requested,
        private readonly int $available,
    ) {
        parent::__construct(
            "Insufficient stock for '{$productName}': requested {$requested}, available {$available}"
        );
    }

    public function productName(): string
    {
        return $this->productName;
    }

    public function requested(): int
    {
        return $this->requested;
    }

    public function available(): int
    {
        return $this->available;
    }
}
