<?php

namespace App\Http\Controllers;

use App\Jobs\DailyStockReportJob;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DailyStockReportController extends Controller
{
    /**
     * App-open catch-up for the daily stock summary.
     *
     * The 02:30 UTC (09:00 Myanmar) schedule is the preferred send time; when
     * that slot is missed — device off, queue worker down — this endpoint is
     * called once per app launch with an active session and dispatches the
     * job instead, as long as today's claim is still unset. The job's claim
     * makes the dispatch idempotent, so racing the schedule is harmless.
     */
    public function check(): JsonResponse
    {
        $now = now();

        // Before today's scheduled time the schedule gets first shot; the
        // time is shared with the scheduler through the job's constant.
        [$hour, $minute] = array_map('int', explode(':', DailyStockReportJob::SCHEDULE_TIME));
        if ($now->lt($now->copy()->setTime($hour, $minute))) {
            return response()->json(['dispatched' => false, 'reason' => 'before_schedule']);
        }

        $date = $now->toDateString();
        foreach (Inventory::pluck('shop_id') as $shopId) {
            if (!Cache::has('stock_report:shop_' . $shopId . ':' . $date)) {
                DailyStockReportJob::dispatch();

                return response()->json(['dispatched' => true]);
            }
        }

        return response()->json(['dispatched' => false, 'reason' => 'already_sent']);
    }
}
