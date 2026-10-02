<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            // Null when no account matches the email that was tried. That is
            // the interesting case: a guess that never hit a real account is
            // invisible unless the row survives the failed lookup.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            // The Sanctum token name, e.g. "terminal-07". Recorded on success
            // so an incident can be traced back to the device that signed in.
            $table->string('device_name')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['email', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};