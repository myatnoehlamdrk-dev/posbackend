<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the product read/write hot paths.
 *
 * `products.name` and `suppliers.name` carry no index anywhere, yet both are
 * hit on the create path (`findByName` / `resolveOrCreate`) on every save.
 *
 * The foreign-key columns are the TiDB-specific half. TiDB does not enforce
 * foreign keys and therefore does not create the implicit index MySQL/InnoDB
 * adds for them — so the product list's `whereHas('package.category…')`
 * chain (products.package_id -> packages.category_id ->
 * categories.inventory_id -> inventories.shop_id) and the
 * `users.id = products.created_by` scope were correlated scans. On InnoDB
 * those indexes already exist, so they are only added when the server is
 * actually TiDB (detected from `SELECT version()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Product create merges by name; search sorts/filters by it.
            $table->index('name');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            // resolveOrCreate() looks suppliers up by name on every save.
            $table->index('name');
        });

        if (!$this->serverIsTiDB()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->index('package_id');
            $table->index('supplier_id');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->index('category_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('inventory_id');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->index('shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        if (!$this->serverIsTiDB()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['package_id']);
            $table->dropIndex(['supplier_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['inventory_id']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex(['shop_id']);
        });
    }

    /**
     * TiDB reports its version like "8.0.11-TiDB-v8.5.0"; MySQL does not
     * contain "TiDB". Anything unreadable (sqlite in dev/tests) counts as
     * "not TiDB", so the FK indexes stay off where they already exist.
     */
    private function serverIsTiDB(): bool
    {
        try {
            return str_contains((string) DB::scalar('SELECT version()'), 'TiDB');
        } catch (\Throwable) {
            return false;
        }
    }
};
