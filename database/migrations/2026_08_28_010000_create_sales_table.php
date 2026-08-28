<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // cashier
            $table->string('user_name');
            $table->string('voucher_no')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('order_id')->nullable();
            $table->integer('quantity_sold')->default(0);
            $table->integer('total_price')->default(0);
            $table->json('price_per_unit')->nullable(); // multi list: [{"name":"burger","price":10},{"name":"cocacola","price":20}]
            $table->string('customer_name')->nullable();
            $table->string('pay_method')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
