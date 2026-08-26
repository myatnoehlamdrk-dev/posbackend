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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete(); // FK -> shops
            $table->string('name');
            $table->string('email')->unique();
            $table->string('type')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('social')->nullable();
            $table->string('image')->nullable(); // user photo (imgbb url)
            $table->string('image_delete_url')->nullable(); // imgbb delete_url (for cleanup)
            $table->string('role')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->nullable();
            $table->string('nrc_no')->nullable();
            $table->string('billing_way')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->boolean('active_status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
