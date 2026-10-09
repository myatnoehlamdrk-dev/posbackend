<?php

namespace App\Console\Commands;

use App\Services\DailyStockReportCatchUp;
use Illuminate\Console\Command;

/**
 * Server-side safety net for the daily stock summary: the dailyAt fire at
 * SCHEDULE_TIME happens once, so if that minute is missed (machine asleep,
 * worker restarting) nothing would send until an app opened again. This
 * runs every minute via the scheduler and dispatches the report as soon as
 * SCHEDULE_TIME has passed and today's claim is still missing — no app
 * involved. Silent when there is nothing to do.
 */
class StockReportCatchup extends Command
{
    protected $signature = 'stock-report:catchup';

    protected $description = 'Dispatch today\'s daily stock report after SCHEDULE_TIME if it has not been sent yet (server-side, runs every minute)';

    public function handle(DailyStockReportCatchUp $catchUp): int
    {
        if ($catchUp->run() === 'dispatched') {
            $this->info('Daily stock report dispatched (today\'s claim was missing after SCHEDULE_TIME).');
        }

        return self::SUCCESS;
    }
}
