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

    public function isVariantAvailable(Product $product, int $quantity, ?string $size = null, ?string $color = null): bool
    {
        $variants = $product->variants ?? [];
        if (empty($variants)) {
            return false;
        }

        foreach ($variants as $variant) {
            $matchSize = ($size === null || $size === '' || ($variant['size'] ?? '') === $size);
            $matchColor = ($color === null || $color === '' || ($variant['color'] ?? '') === $color);
            if ($matchSize && $matchColor) {
                return ($variant['quantity'] ?? 0) >= $quantity;
            }
        }

        return false;
    }

    public function deduct(Product $product, int $quantity, ?string $size = null, ?string $color = null): void
    {
        $variants = $product->variants ?? [];

        if (empty($variants)) {
            return;
        }

        DB::transaction(function () use ($product, $quantity, $size, $color, &$variants) {
            $freshProduct = Product::lockForUpdate()->find($product->id);
            $variants = $freshProduct->variants ?? [];

            $remaining = $quantity;
            $updatedVariants = [];

            if ($size !== null || $color !== null) {
                $matchedIndex = null;
                foreach ($variants as $index => $variant) {
                    $matchSize = ($size === null || $size === '' || ($variant['size'] ?? '') === $size);
                    $matchColor = ($color === null || $color === '' || ($variant['color'] ?? '') === $color);
                    if ($matchSize && $matchColor) {
                        $matchedIndex = $index;
                        break;
                    }
                }

                if ($matchedIndex !== null) {
                    $variant = $variants[$matchedIndex];
                    $variantQty = $variant['quantity'] ?? 0;
                    $deductFromVariant = min($variantQty, $remaining);

                    foreach ($variants as $index => $variant) {
                        if ($index === $matchedIndex) {
                            $updatedVariants[] = array_merge($variant, [
                                'quantity' => ($variant['quantity'] ?? 0) - $deductFromVariant,
                                '_last_deducted' => true,
                            ]);
                        } else {
                            $updatedVariants[] = $variant;
                        }
                    }
                } else {
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
                            $updatedVariants[count($updatedVariants) - 1]['_last_deducted'] = true;
                        }
                    }
                }
            } else {
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
                        $updatedVariants[count($updatedVariants) - 1]['_last_deducted'] = true;
                    }
                }
            }

            $freshProduct->update(['variants' => $updatedVariants]);
        });
    }

    public function restore(Product $product, int $quantity, ?string $size = null, ?string $color = null): void
    {
        $variants = $product->variants ?? [];

        if (empty($variants)) {
            $product->update(['variants' => [['quantity' => $quantity]]]);
            return;
        }

        DB::transaction(function () use ($product, $quantity, $size, $color, &$variants) {
            $freshProduct = Product::lockForUpdate()->find($product->id);
            $variants = $freshProduct->variants ?? [];

            $updatedVariants = $variants;
            $restored = false;

            if ($size !== null || $color !== null) {
                foreach ($updatedVariants as $index => &$variant) {
                    $matchSize = ($size === null || $size === '' || ($variant['size'] ?? '') === $size);
                    $matchColor = ($color === null || $color === '' || ($variant['color'] ?? '') === $color);
                    if ($matchSize && $matchColor) {
                        $variant['quantity'] = ($variant['quantity'] ?? 0) + $quantity;
                        $restored = true;
                        break;
                    }
                }
                unset($variant);
            }

            if (!$restored) {
                foreach ($updatedVariants as $index => &$variant) {
                    if (!empty($variant['_last_deducted'])) {
                        $variant['quantity'] = ($variant['quantity'] ?? 0) + $quantity;
                        unset($variant['_last_deducted']);
                        $restored = true;
                        break;
                    }
                }
                unset($variant);
            }

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

            if (!$restored && !empty($updatedVariants)) {
                $updatedVariants[0]['quantity'] = ($updatedVariants[0]['quantity'] ?? 0) + $quantity;
            }

            $freshProduct->update(['variants' => $updatedVariants]);
        });
    }
}
