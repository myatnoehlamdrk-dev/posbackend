<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            // Ownership moves from "the creator's user row" to the item itself.
            // Nullable so a historical orphan (user_id null, which the repair
            // on 2026-10-19 fixed for the one existing row) can never fail
            // this migration; new rows always get a value — the controller
            // refuses to create one without a shop. No foreign key to shops on
            // purpose: TiDB does not enforce foreign keys, so a constraint
            // would be decoration that some versions reject on ALTER.
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->index('shop_id');
        });

        // Backfill from the link this column replaces: the index query used to
        // scope through the creator's user row (whereHas('user')), which made
        // items silently invisible the moment that link broke. Rows whose user
        // is gone have no shop to attribute and stay null.
        DB::statement(
            'UPDATE purchase_items
             JOIN users ON users.id = purchase_items.user_id
             SET purchase_items.shop_id = users.shop_id'
        );
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropIndex(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
