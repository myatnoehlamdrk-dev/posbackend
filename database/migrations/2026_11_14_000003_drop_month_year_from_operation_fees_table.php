<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_fees', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'year', 'month']);
            $table->dropColumn(['month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::table('operation_fees', function (Blueprint $table) {
            $table->unsignedSmallInteger('month')->default(1)->after('shop_id');
            $table->unsignedSmallInteger('year')->default(2026)->after('month');
            $table->index(['shop_id', 'year', 'month']);
        });
    }
};
