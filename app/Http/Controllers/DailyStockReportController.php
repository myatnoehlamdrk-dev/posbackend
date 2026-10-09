<?php

namespace App\Http\Controllers;

use App\Services\DailyStockReportCatchUp;
use Illuminate\Http\JsonResponse;

class DailyStockReportController extends Controller
{
    /**
     * App-open catch-up for the daily stock summary.
     *
     * The 02:30 UTC (09:00 Myanmar) schedule is the preferred send time; when
     * that slot was missed — device off, queue worker down — this endpoint is
     * called by the app on open, and the stock-report:catchup scheduler entry
     * every minute, so the day's push still goes out without waiting for the
     * app. Both share DailyStockReportCatchUp; the job's claim (held only
     * after a delivered push) keeps them idempotent.
     */
    public function check(DailyStockReportCatchUp $catchUp): JsonResponse
    {
        return match ($catchUp->run()) {
            'dispatched' => response()->json(['dispatched' => true]),
            'before_schedule' => response()->json(['dispatched' => false, 'reason' => 'before_schedule']),
            default => response()->json(['dispatched' => false, 'reason' => 'already_sent']),
        };
    }
}
