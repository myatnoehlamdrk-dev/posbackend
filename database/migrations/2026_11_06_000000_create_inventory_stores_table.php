<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_stores', function (Blueprint $table) {
            $table->id();

            // Ownership / tenancy
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Product identity
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('universal_identifier')->nullable(); // UPC / EAN / ISBN

            // Product attributes
            $table->string('name');
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('location_id')->nullable();

            // Inventory attributes
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_available')->default(0);
            $table->integer('safety_stock')->default(0);
            $table->integer('reorder_point')->default(0);

            // Pricing
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('retail_price', 12, 2)->default(0);

            // Physical attributes
            $table->string('dimensions')->nullable(); // L x W x H
            $table->decimal('weight', 10, 3)->nullable();

            // Batch / expiry
            $table->string('batch_number')->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('best_before_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stores');
    }
};
