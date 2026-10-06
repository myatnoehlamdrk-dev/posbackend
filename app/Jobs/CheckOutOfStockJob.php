<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class CheckOutOfStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(FcmService $fcmService): void
    {
        $products = Product::where('stock', '<=', 0)
            ->where('active', true)
            ->with('package.category.inventory')
            ->get();

        foreach ($products as $product) {
            $shopId = $product->shop_id ?? $product->package?->shop_id;
            if (!$shopId) {
                continue;
            }

            $cacheKey = "stock_out_notified:shop_{$shopId}:product_{$product->id}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            $title = 'Out of Stock';
            $body = $product->name . ' is out of stock';

            $tokens = \App\Models\FcmToken::where('shop_id', $shopId)
                ->where('active', true)
                ->pluck('token');

            foreach ($tokens as $token) {
                $fcmService->sendToToken($token, $title, $body, [
                    'type' => 'stock_out',
                    'product_id' => (string) $product->id,
                    'product_name' => $product->name,
                ]);
            }

            Cache::put($cacheKey, true, now()->addHours(24));
        }
    }
}