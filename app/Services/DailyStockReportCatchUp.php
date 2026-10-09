<?php

namespace App\Services;

use App\Jobs\DailyStockReportJob;
use App\Models\Inventory;
use Illuminate\Support\Facades\Cache;

/**
 * Shared "was today's report missed?" check for the two catch-up triggers:
 * the app-open /notifications/daily-report/check endpoint and the
 * every-minute stock-report:catchup scheduler entry.
 *
 * The scheduled dailyAt fire at SCHEDULE_TIME is the preferred send; this
 * only runs after that time has passed and only when at least one shop has
 * no claim for today, so it can dispatch at most once per shop per day.
 */
class DailyStockReportCatchUp
{
    /**
     * Dispatches DailyStockReportJob when due and reports what happened:
     *
     * 'before_schedule' — SCHEDULE_TIME not reached yet, the schedule gets
     *                     first shot;
     * 'dispatched'      — at least one shop missing today's claim, job queued;
     * 'already_sent'    — every shop claimed for today, nothing to do.
     */
    public function run(): string
    {
        $now = now();

        [$hour, $minute] = array_map('intval', explode(':', DailyStockReportJob::SCHEDULE_TIME));
        if ($now->lt($now->copy()->setTime($hour, $minute))) {
            return 'before_schedule';
        }

        $date = $now->toDateString();
        foreach (Inventory::pluck('shop_id') as $shopId) {
            if (!Cache::has('stock_report:shop_' . $shopId . ':' . $date)) {
                DailyStockReportJob::dispatch();

                return 'dispatched';
            }
        }

        return 'already_sent';
    }
}
