<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('voucher_no')->nullable();
            $table->string('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('order_id')->nullable();
            $table->string('quantity_sold')->nullable();
            $table->integer('total_price')->default(0);
            $table->json('price_per_unit')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('pay_method')->nullable();
            $table->json('items');
            $table->integer('grand_total')->default(0);
            $table->integer('discount')->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
