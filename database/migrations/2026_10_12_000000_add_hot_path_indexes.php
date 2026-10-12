<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the hot read paths, added after profiling the dashboard and
 * list endpoints against a populated database.
 *
 * sales.user_id / sale_items.sale_id / orders.user_id already carry
 * implicit InnoDB indexes via their foreign keys — only the non-FK filter
 * and sort columns are added here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Sale list and every dashboard aggregate sort/filter on
            // created_at within a shop scope.
            $table->index('created_at');
            // Voucher lookups (receipt reprints, duplicate checks).
            $table->index('voucher_no');
            // Order-conversion audits join sales back to the source draft.
            $table->index('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Draft-count widgets poll status = 'draft' constantly.
            $table->index('status');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            // Top-products dashboards filter sale_items by created_at.
            $table->index('created_at');
        });

        Schema::table('products', function (Blueprint $table) {
            // Product lists filter active = true before any other scope.
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['voucher_no']);
            $table->dropIndex(['order_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['active']);
        });
    }
};
