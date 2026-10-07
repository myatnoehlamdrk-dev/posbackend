<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * "Stock Restored" push: a sale item was deleted and the product has stock
 * again (green cart tile in the Alert tab; tapping opens the product).
 *
 * The dedupe slot is keyed by the EVENT id instead of the product: the same
 * product may legitimately be restored many times a day, while the double-pop
 * problem (TiDB ignores FOR UPDATE SKIP LOCKED) re-runs the exact same job
 * row — which carries the same event id and is dropped.
 */
class SendStockRestoredNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?int $shopId,
        private readonly string $productId,
        private readonly string $productName,
        private readonly int $restoredQty,
        private readonly int $totalStock,
        private readonly string $eventId,
    ) {}

    public function handle(FcmService $fcmService): void
    {
        if (!$this->shopId) {
            return;
        }

        if (!$this->claimEvent()) {
            return;
        }

        $fcmService->sendToShop(
            $this->shopId,
            'Stock Restored',
            $this->productName . ' +' . $this->restoredQty . ' (now ' . $this->totalStock . ')',
            [
                'type' => 'stock_restored',
                'category' => 'alert',
                'product_id' => $this->productId,
                'product_name' => $this->productName,
                'restored' => (string) $this->restoredQty,
                'total' => (string) $this->totalStock,
            ]
        );
    }

    /**
     * Atomically claims this restore event. See SendStockOutNotificationJob
     * for why the claim lives inside the job: TiDB lets two workers pop the
     * same row, so only the worker holding the lock sends.
     */
    private function claimEvent(): bool
    {
        $key = 'stock_restored:event_' . $this->eventId;
        $lock = Cache::lock($key . ':lock', 15);

        if (!$lock->get()) {
            return false;
        }

        try {
            if (Cache::has($key)) {
                return false;
            }
            Cache::put($key, true, now()->addHours(24));
            return true;
        } finally {
            $lock->release();
        }
    }
}
