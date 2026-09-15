<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['sale_items', 'purchase_items', 'products', 'categories', 'packages'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->nullOnDelete()->after('id');
                $table->foreignId('updated_by')->nullable()->nullOnDelete()->after('created_by');
            });
        }
    }

    public function down(): void
    {
        $tables = ['sale_items', 'purchase_items', 'products', 'categories', 'packages'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            });
        }
    }
};
