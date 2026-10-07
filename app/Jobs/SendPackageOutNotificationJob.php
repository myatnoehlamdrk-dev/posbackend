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
 * Fires when every product inside a package has gone out of stock — the
 * package itself can no longer be sold. Claims its slot atomically, like
 * SendStockOutNotificationJob, because TiDB lets several queue:work workers
 * pop the same job row.
 */
class SendPackageOutNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?int $shopId,
        private readonly int $packageId,
        private readonly string $packageName,
    ) {}

    public function handle(FcmService $fcmService): void
    {
        if (!$this->shopId) {
            return;
        }

        if (!$this->claimNotificationSlot()) {
            return;
        }

        $fcmService->sendToShop(
            $this->shopId,
            'Package Out of Stock',
            $this->packageName . ' has no products left in stock',
            [
                'type' => 'package_out',
                'category' => 'alert',
                'package_id' => (string) $this->packageId,
                'package_name' => $this->packageName,
            ]
        );
    }

    private function claimNotificationSlot(): bool
    {
        $key = "package_out_notified:shop_{$this->shopId}:package_{$this->packageId}";
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
