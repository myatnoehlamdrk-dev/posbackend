<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stores', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('best_before_date');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stores', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
