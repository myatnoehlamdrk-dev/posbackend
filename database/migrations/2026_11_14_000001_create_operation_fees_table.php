<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 50)->default('other');
            $table->string('calculation', 50)->default('fixedPerMonth');
            $table->decimal('rate', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('icon', 50)->default('other');
            $table->string('tint', 50)->default('gray');
            $table->timestamps();
        });

        Schema::create('operation_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('allocation_method', 50)->default('revenue');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_fee_settings');
        Schema::dropIfExists('operation_fees');
    }
};
