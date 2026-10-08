<?php

namespace App\Jobs;

use App\Models\Inventory;
use App\Models\Package;
use App\Models\Product;
use App\Models\StockAlert;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Once-a-day shop report: how many products are out of stock, how many are
 * low stock and which packages have no stocked product left, sent as ONE
 * push whose payload carries the per-product and per-package lists for the
 * in-app detail screen.
 *
 * Reaching SCHEDULE_TIME always produces the push: the scheduled fire is
 * never gated by a claim, so today's claim (set or not) cannot stop it and
 * repeated runs of the scheduled job may send more than once.
 *
 * `stock_report:shop_*` gates only the catch-up/manual path (once a day);
 * the schedule sets it too, so after the scheduled send the catch-up stops
 * for the rest of the day.
 */
class DailyStockReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * FCM data payloads are capped (~4KB), so the list in the payload is
     * truncated; the counts in the body always cover every product.
     */
    private const MAX_LISTED_PRODUCTS = 40;

    /// Matches the dashboard's low-stock tier when a product has no
    /// per-product override in stock_alerts.
    private const DEFAULT_LOW_STOCK_THRESHOLD = 5;

    /**
     * The one send time of the day (24h, UTC — the app timezone). Shared by
     * the scheduler in routes/console.php and the app-open catch-up's
     * "after schedule" cutoff, so moving the time is a single edit.
     * 02:30 UTC = 09:00 Myanmar (UTC+6:30).
     */
    public const SCHEDULE_TIME = '09:03';

    /**
     * @param bool $fromSchedule true when dispatched by the daily scheduler;
     *  the send is then not gated at all, so reaching SCHEDULE_TIME always
     *  produces the push — even when a manual test or the catch-up already
     *  claimed today.
     */
    public function __construct(public bool $fromSchedule = false) {}

    public function handle(FcmService $fcmService): void
    {
        $products = Product::where('active', true)
            ->with('package.category.inventory')
            ->get();

        $alertsByShop = StockAlert::all()
            ->groupBy(fn (StockAlert $alert) => (string) $alert->shop_id);

        $byShop = $products->groupBy(
            fn (Product $product) => (string) ($product->package?->category?->inventory?->shop_id ?? '')
        );

        $productsByPackage = $products->groupBy(
            fn (Product $product) => (string) ($product->package_id ?? '')
        );

        // Active packages grouped by shop. A package counts as out when it has
        // no active products at all or every one of them is at zero stock —
        // the same rule the package-empty push uses.
        $packagesByShop = Package::where('active', true)
            ->with('category.inventory')
            ->whereHas('category.inventory')
            ->get()
            ->groupBy(fn (Package $pkg) => (string) ($pkg->category?->inventory?->shop_id ?? ''));

        // Every known shop — including ones without active products — so an
        // idle shop still gets its once-a-day claim and stops being retried
        // by the app-open catch-up.
        $shopIds = Inventory::pluck('shop_id')
            ->merge($byShop->keys())
            ->merge($packagesByShop->keys())
            ->map(fn ($id) => (string) $id)
            ->filter(fn (string $id) => $id !== '')
            ->unique()
            ->values();

        foreach ($shopIds as $shopId) {
            $items = $byShop->get($shopId, collect());
            [$out, $low] = $this->classify($items, $alertsByShop->get($shopId));

            $packagesOut = [];
            foreach ($packagesByShop->get($shopId, collect()) as $pkg) {
                $pkgProducts = $productsByPackage->get((string) $pkg->id, collect());
                if (
                    $pkgProducts->isEmpty()
                    || $pkgProducts->every(fn (Product $product) => $product->getAvailableStock() <= 0)
                ) {
                    $packagesOut[] = [
                        'id' => (string) $pkg->id,
                        'name' => (string) $pkg->name,
                        'kind' => 'package_out',
                    ];
                }
            }

            // The scheduled fire at SCHEDULE_TIME is never gated: today's
            // claim yes or no, reaching the schedule time produces the push
            // (repeated runs at that time may therefore send more than
            // once). It still sets the regular claim so the app-open
            // catch-up stops for the rest of the day. The catch-up/manual
            // path stays once-a-day through that claim, which is set even
            // with nothing to report so a quiet day stops redispatching
            // until tomorrow.
            $date = now()->toDateString();
            if ($this->fromSchedule) {
                $this->claim('stock_report:shop_' . $shopId . ':' . $date);
            } elseif (!$this->claim('stock_report:shop_' . $shopId . ':' . $date)) {
                continue;
            }

            if (empty($out) && empty($low) && empty($packagesOut)) {
                continue;
            }

            $body = count($out) . ' out of stock, ' . count($low) . ' low stock';
            if (!empty($packagesOut)) {
                $body .= ', ' . count($packagesOut) . ' packages out';
            }

            $fcmService->sendToShop(
                (int) $shopId,
                'Daily Stock Report',
                $body,
                [
                    'type' => 'stock_report',
                    'category' => 'alert',
                    'out_count' => (string) count($out),
                    'low_count' => (string) count($low),
                    'package_out_count' => (string) count($packagesOut),
                    'products' => (string) json_encode(
                        array_slice([...$out, ...$low], 0, self::MAX_LISTED_PRODUCTS)
                    ),
                    'packages' => (string) json_encode(
                        array_slice($packagesOut, 0, self::MAX_LISTED_PRODUCTS)
                    ),
                ]
            );
        }
    }

    /**
     * Splits one shop's products into out-of-stock and low-stock entries.
     *
     * Availability goes through getAvailableStock() (not the stock column)
     * so bundle products report their component-derived stock. A stock_alerts
     * row overrides the default threshold; is_active = false opts the product
     * out of the low-stock list entirely.
     *
     * @return array{0: array<int, array<string, string>>, 1: array<int, array<string, string>>}
     */
    private function classify($items, $alerts)
    {
        $out = [];
        $low = [];

        foreach ($items as $product) {
            $stock = $product->getAvailableStock();

            if ($stock <= 0) {
                $out[] = [
                    'id' => (string) $product->id,
                    'name' => (string) $product->name,
                    'kind' => 'out',
                    'stock' => (string) $stock,
                    'threshold' => '',
                ];
                continue;
            }

            $alert = $alerts?->first(
                fn (StockAlert $a) => (int) $a->product_id === (int) $product->id
            );
            if ($alert !== null && !$alert->is_active) {
                continue;
            }

            $threshold = $alert?->threshold ?? self::DEFAULT_LOW_STOCK_THRESHOLD;
            if ($stock <= $threshold) {
                $low[] = [
                    'id' => (string) $product->id,
                    'name' => (string) $product->name,
                    'kind' => 'low',
                    'stock' => (string) $stock,
                    'threshold' => (string) $threshold,
                ];
            }
        }

        return [$out, $low];
    }

    private function claim(string $key): bool
    {
        $lock = Cache::lock($key . ':lock', 15);

        if (!$lock->get()) {
            return false;
        }

        try {
            if (Cache::has($key)) {
                return false;
            }
            Cache::put($key, true, now()->addDays(2));
            return true;
        } finally {
            $lock->release();
        }
    }
}
