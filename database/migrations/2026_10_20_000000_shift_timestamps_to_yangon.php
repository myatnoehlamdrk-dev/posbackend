<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-base every stored timestamp from UTC to Asia/Yangon (UTC+6:30).
     *
     * The app was configured `'timezone' => 'UTC'`, so Laravel wrote `now()` --
     * which it had already put in UTC -- straight into `created_at`. A sale rung
     * up at 2:25pm was stored as `07:25` and read back as "7:25 AM" on every
     * screen. The config is now `Asia/Yangon`, which fixes everything written
     * *after* this point, but it cannot retroactively fix rows already in the
     * table: those are UTC instants and have to be moved.
     *
     * This shifts both directions of every timestamp column it can find. It is
     * deliberately driven off `information_schema` rather than a hand-written
     * table list: the schema has 22 tables carrying datetime columns and a
     * hard-coded list would silently miss any added later, leaving half the app
     * on UTC -- the exact failure this is meant to end.
     *
     * Not shifted, on purpose:
     *  - `jobs`/`failed_jobs`: framework queues. Their timestamps are compared
     *    against `time()` by the queue worker, not displayed, so moving them
     *    would break retry/backoff arithmetic for no user-visible gain.
     *  - `migrations`: the migration bookkeeping itself.
     *
     * Reversible. `down()` subtracts the same interval, and the pre-change dump
     * lives in `D:/POS/backups/tz_backup_*.sql`.
     */
    private const SHIFT = '6:30';

    /** Framework bookkeeping whose timestamps are compared, never displayed. */
    private const SKIP_TABLES = ['migrations', 'jobs', 'failed_jobs', 'cache', 'cache_locks', 'sessions'];

    public function up(): void
    {
        $this->shift('+', self::SHIFT);
    }

    public function down(): void
    {
        $this->shift('-', self::SHIFT);
    }

    private function shift(string $sign, string $interval): void
    {
        $columns = $this->timestampColumns();

        // MySQL's INTERVAL grammar does not accept a leading sign (`INTERVAL
        // +6:30 MINUTE` is a syntax error), so the direction is expressed as a
        // whole number of minutes: +390 or -390.
        $minutes = $this->asMinutes($interval);
        $delta = $sign === '-' ? -$minutes : $minutes;

        foreach ($columns as $table => $cols) {
            if ($cols === []) {
                continue;
            }

            // One UPDATE per table so the whole row set moves atomically; a
            // partial shift would leave rows describing an instant that never
            // existed.
            $assignments = array_map(
                fn (string $c) => "`{$c}` = DATE_ADD(`{$c}`, INTERVAL {$delta} MINUTE)",
                $cols,
            );

            // NULL-safe: DATE_ADD(NULL, ...) is NULL, so nullable columns
            // (`last_login_at`, `expires_at`) keep their NULLs.
            DB::statement(
                'UPDATE `' . $table . '` SET ' . implode(', ', $assignments) .
                ' WHERE ' . implode(' OR ', array_map(fn (string $c) => "`{$c}` IS NOT NULL", $cols))
            );
        }
    }

    /** `'6:30'` -> 390. */
    private function asMinutes(string $interval): int
    {
        [$hours, $mins] = array_pad(explode(':', $interval), 2, '0');

        return ((int) $hours) * 60 + (int) $mins;
    }

    /**
     * Every datetime/timestamp column, grouped by table.
     *
     * @return array<string, list<string>>
     */
    private function timestampColumns(): array
    {
        $rows = DB::select(
            'select table_name, column_name from information_schema.columns
             where table_schema = ? and data_type in (?, ?)
             order by table_name, ordinal_position',
            [DB::getDatabaseName(), 'timestamp', 'datetime'],
        );

        $grouped = [];

        foreach ($rows as $row) {
            $table = $row->TABLE_NAME ?? $row->table_name;
            $column = $row->COLUMN_NAME ?? $row->column_name;

            if (in_array($table, self::SKIP_TABLES, true) || ! Schema::hasTable($table)) {
                continue;
            }

            $grouped[$table][] = $column;
        }

        return $grouped;
    }
};
