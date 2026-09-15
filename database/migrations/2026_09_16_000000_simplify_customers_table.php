<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'address',
                'tax_id',
                'total_purchases',
                'total_spent',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');
            $table->string('tax_id')->nullable()->after('address');
            $table->integer('total_purchases')->default(0)->after('tax_id');
            $table->integer('total_spent')->default(0)->after('total_purchases');
        });
    }
};
