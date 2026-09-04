<?php

namespace App\Factories;

use App\Calculators\BundleStockCalculator;
use App\Calculators\SimpleStockCalculator;
use App\Calculators\VariantStockCalculator;
use App\Contracts\StockCalculatorInterface;
use App\Models\Product;

class StockCalculatorFactory
{
    private static array $calculators = [
        'simple' => SimpleStockCalculator::class,
        'bundle' => BundleStockCalculator::class,
        'variant' => VariantStockCalculator::class,
    ];

    public static function make(Product $product): StockCalculatorInterface
    {
        $type = (string) ($product->getAttributes()['product_type'] ?? 'simple');
        $calculatorClass = self::$calculators[$type] ?? SimpleStockCalculator::class;

        return new $calculatorClass();
    }

    public static function register(string $type, string $calculatorClass): void
    {
        self::$calculators[$type] = $calculatorClass;
    }
}
