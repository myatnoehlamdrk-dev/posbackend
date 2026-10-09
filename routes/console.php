<?php

use App\Jobs\DailyStockReportJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stock-report:send', function () {
    $shopIds = DB::table('inventories')->distinct()->pluck('shop_id');
    foreach ($shopIds as $shopId) {
        Cache::forget('stock_report:shop_' . $shopId . ':' . now()->toDateString());
    }
    DailyStockReportJob::dispatchSync();
    $this->info('Report claims cleared for ' . $shopIds->count() . ' shop(s); summary push sent (test helper).');
})->purpose('Send the daily stock summary now, ignoring the once-per-day claim (for testing)');

// Guaranteed daily send: reaching SCHEDULE_TIME always produces the push —
// the scheduled fire consults no claim, so today's claim (set or not) and
// anything earlier in the day cannot block it. The time lives in
// DailyStockReportJob::SCHEDULE_TIME (shared with the app-open catch-up).
Schedule::job(new DailyStockReportJob(fromSchedule: true))
    ->dailyAt(DailyStockReportJob::SCHEDULE_TIME);

// Server-side safety net: the dailyAt fire happens only once, so if that
// minute is missed (machine asleep, worker restarting) nothing would send
// until an app opened again. Every minute after SCHEDULE_TIME this dispatches
// the report while today's claim is missing — no app needs to be open.
Schedule::command('stock-report:catchup')
    ->everyMinute()
    ->withoutOverlapping();
