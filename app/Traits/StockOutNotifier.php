<?php

namespace App\Traits;

use App\Jobs\SendPackageOutNotificationJob;
use App\Jobs\SendStockOutNotificationJob;
use App\Jobs\SendStockRestoredNotificationJob;
use App\Models\Package;
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
            // Neither products nor packages carries a shop_id column; the
            // shop lives on the inventory at the end of
            // product -> package -> category -> inventory.
            //
            // The 24h claim is deliberately NOT made here: with several
            // queue:work workers running, TiDB lets both workers pop the
            // same job row (SKIP LOCKED is not enforced), so the claim
            // must happen atomically inside the job that actually sends —
            // otherwise every stock-out push arrives twice. This check is
            // only a fast path to avoid piling up duplicate jobs.
            $shopId = $product->package?->category?->inventory?->shop_id;
            if ($shopId && !$this->wasRecentlyNotified($shopId, $product->id)) {
                SendStockOutNotificationJob::dispatch($shopId, $product->name, (string) $product->id);
            }
        }

        if ($newStock <= 0) {
            $this->notifyIfPackageEmpty($product);
        }
    }

    protected function notifyIfAlreadyOut(Product $product): void
    {
        $shopId = $product->package?->category?->inventory?->shop_id;
        if ($shopId && !$this->wasRecentlyNotified($shopId, $product->id)) {
            SendStockOutNotificationJob::dispatch($shopId, $product->name, (string) $product->id);
        }

        $this->notifyIfPackageEmpty($product);
    }

    /**
     * Pushes "package out of stock" when the package can no longer be sold:
     * every active product inside it has hit 0, or the package has no active
     * products at all (all removed / moved to another package).
     *
     * Same double-pop rule as the product push: the Cache::has check below
     * is only a fast path, SendPackageOutNotificationJob claims the 24h slot
     * atomically before sending.
     */
    protected function notifyIfPackageEmpty(Product $product): void
    {
        $package = $product->package;
        if ($package) {
            $this->notifyPackageIfEmpty($package);
        }
    }

    protected function notifyPackageIfEmpty(Package $package): void
    {
        if ((int) $package->active !== 1) {
            return;
        }

        // A package with no active products falls straight through the loop
        // and pushes too — "no products" and "no stock" are both unsellable.
        $siblings = $package->products()->where('active', true)->get();
        foreach ($siblings as $sibling) {
            if ($sibling->getAvailableStock() > 0) {
                return;
            }
        }

        $shopId = $package->category?->inventory?->shop_id;
        if (!$shopId) {
            return;
        }

        $key = "package_out_notified:shop_{$shopId}:package_{$package->id}";
        if (Cache::has($key)) {
            return;
        }

        SendPackageOutNotificationJob::dispatch((int) $shopId, (int) $package->id, (string) $package->name);
    }

    /**
     * A sale item was deleted and the stock came back: push "Stock Restored"
     * ("Product +3 (now 7)") and — because the product is sellable again —
     * forget the out-of-stock claims, so the NEXT time the product (or its
     * package) empties the push is not suppressed by today's notification.
     *
     * $eventId must uniquely identify this restore (sale id + product id,
     * or the sale-item id): the job dedupes double-pops on it while letting
     * repeated restores of the same product through.
     */
    protected function notifyStockRestored(Product $product, int $restoredQty, string $eventId): void
    {
        if (!(int) $product->active) {
            return;
        }

        $shopId = $product->package?->category?->inventory?->shop_id;
        if (!$shopId) {
            return;
        }

        SendStockRestoredNotificationJob::dispatch(
            (int) $shopId,
            (string) $product->id,
            (string) $product->name,
            $restoredQty,
            $product->getAvailableStock(),
            $eventId,
        );

        $this->clearRecoveryClaims($product);
    }

    /**
     * A product holding stock again is no longer "out", and its package has
     * something sellable — forgetting both 24h claims lets a later real
     * emptying push instead of being silently swallowed by yesterday's.
     */
    protected function clearRecoveryClaims(Product $product): void
    {
        if ($product->getAvailableStock() <= 0) {
            return;
        }

        $shopId = $product->package?->category?->inventory?->shop_id;
        if (!$shopId) {
            return;
        }

        Cache::forget("stock_out_notified:shop_{$shopId}:product_{$product->id}");
        if ($product->package) {
            Cache::forget("package_out_notified:shop_{$shopId}:package_{$product->package->id}");
        }
    }

    private function wasRecentlyNotified(int $shopId, int $productId): bool
    {
        $key = "stock_out_notified:shop_{$shopId}:product_{$productId}";
        return Cache::has($key);
    }
}
