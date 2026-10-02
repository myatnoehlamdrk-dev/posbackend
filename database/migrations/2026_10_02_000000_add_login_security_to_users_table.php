<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Throttle state for credential stuffing. `locked_until` is a
            // timestamp rather than a boolean so the lock expires on its own
            // instead of needing a manual unlock by an admin.
            $table->unsignedTinyInteger('failed_attempts')->default(0)->after('is_verified');
            $table->timestamp('locked_until')->nullable()->after('failed_attempts');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_attempts', 'locked_until', 'last_login_at', 'last_login_ip']);
        });
    }
};