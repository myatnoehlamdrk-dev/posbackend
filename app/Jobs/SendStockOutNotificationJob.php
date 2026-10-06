<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

        $title = 'Out of Stock';
        $body = $this->productName . ' is out of stock';

        $fcmService->sendToShop(
            $this->shopId,
            $title,
            $body,
            [
                'type' => 'stock_out',
                'product_id' => (string) $this->productId,
                'product_name' => $this->productName,
            ]
        );
    }
}
