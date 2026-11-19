<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendStockOutNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?int $shopId,
        private readonly string $productName,
        private readonly string $productId,
    ) {}

    public function handle(FcmService $fcmService): void
    {
        if (!$this->shopId) {
            return;
        }

        if (!$this->claimNotificationSlot()) {
            return;
        }

        $title = 'Out of Stock';
        $body = $this->productName . ' is out of stock';

        $fcmService->sendToShop(
            $this->shopId,
            $title,
            $body,
            [
                'type' => 'stock_out',
                'category' => 'alert',
                'shop_id' => (string) $this->shopId,
                'product_id' => (string) $this->productId,
                'product_name' => $this->productName,
            ]
        );
    }

    /**
     * Atomically claims the 24h "already notified" slot for this product.
     *
     * TiDB does not enforce FOR UPDATE SKIP LOCKED, so with more than one
     * queue:work running both workers can pop the same job row. The file
     * cache lock serialises them; the first one to find the key unset claims
     * it and sends, the duplicate pop sees the key and exits.
     */
    private function claimNotificationSlot(): bool
    {
        $key = "stock_out_notified:shop_{$this->shopId}:product_{$this->productId}";
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
