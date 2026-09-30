<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            // SQLite has no distinct LONGTEXT type, so there is nothing to widen.
            return;
        }

        DB::statement('ALTER TABLE shops MODIFY shop_image LONGTEXT NULL');
        DB::statement('ALTER TABLE users MODIFY image LONGTEXT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE shops MODIFY shop_image VARCHAR(255) NULL');
        DB::statement('ALTER TABLE users MODIFY image VARCHAR(255) NULL');
    }
};
