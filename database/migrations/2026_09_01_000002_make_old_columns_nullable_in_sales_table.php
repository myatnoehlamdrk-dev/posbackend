<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('product_name')->nullable()->change();
            $table->integer('quantity_sold')->default(0)->change();
            $table->integer('total_price')->default(0)->change();
            $table->json('price_per_unit')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('product_name')->nullable(false)->change();
        });
    }
};
