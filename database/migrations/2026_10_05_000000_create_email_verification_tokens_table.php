<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Split from `password_reset_tokens`, which previously held both the
        // registration-verification code and the password-reset code in one
        // column, disambiguated only by string length. Two flows sharing a
        // single row keyed on email means asking for a reset code destroys a
        // pending verification and vice versa, and the two have different
        // expiry rules and different consequences once consumed.
        //
        // One row per email, matching the table it replaces: a re-request
        // overwrites the previous code, which is what the updateOrInsert in
        // OtpService does.
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            // Always a 6-digit numeric code. The password-reset flow reuses
            // this column for a 64-char handoff token, which is precisely why
            // the two belong in separate tables now.
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        // No merge back into `password_reset_tokens`. The rows here are 6-digit
        // codes, and that table cannot now tell them apart from its own
        // 64-char handoff tokens -- see the comment above. Rolling this back
        // leaves `password_reset_tokens` holding only reset tokens, which the
        // pre-split code handles correctly for those flows.
        Schema::dropIfExists('email_verification_tokens');
    }
};
