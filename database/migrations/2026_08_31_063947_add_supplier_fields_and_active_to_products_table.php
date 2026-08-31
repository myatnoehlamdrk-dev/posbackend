<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('supplier_contact')->nullable()->after('supplier_id');
            $table->string('supplier_since')->nullable()->after('supplier_contact');
            $table->string('supplier_address')->nullable()->after('supplier_since');
            $table->boolean('active')->default(true)->after('supplier_address');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['supplier_contact', 'supplier_since', 'supplier_address', 'active']);
        });
    }
};
