<?php

namespace App\Traits;

use App\Jobs\SendStockOutNotificationJob;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

trait StockOutNotifier
{
    protected function notifyIfStockOut(Product $product, int $oldStock, int $newStock): void
    {
        $shouldNotify = false;

        if ($oldStock > 0 && $newStock <= 0) {
            $shouldNotify = true;
        } elseif ($oldStock <= 0 && $newStock <= 0) {
            $shouldNotify = true;
        }

        if ($shouldNotify) {
            $shopId = $product->shop_id ?? $product->package?->shop_id;
            if ($shopId && !$this->wasRecentlyNotified($shopId, $product->id)) {
                $this->markAsNotified($shopId, $product->id);
                SendStockOutNotificationJob::dispatch($shopId, $product->name, (string) $product->id);
            }
        }
    }

    protected function notifyIfAlreadyOut(Product $product): void
    {
        $shopId = $product->shop_id ?? $product->package?->shop_id;
        if ($shopId && !$this->wasRecentlyNotified($shopId, $product->id)) {
            $this->markAsNotified($shopId, $product->id);
            SendStockOutNotificationJob::dispatch($shopId, $product->name, (string) $product->id);
        }
    }

    private function wasRecentlyNotified(int $shopId, int $productId): bool
    {
        $key = "stock_out_notified:shop_{$shopId}:product_{$productId}";
        return Cache::has($key);
    }

    private function markAsNotified(int $shopId, int $productId): void
    {
        $key = "stock_out_notified:shop_{$shopId}:product_{$productId}";
        Cache::put($key, true, now()->addHours(24));
    }
}
