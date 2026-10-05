<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move pending registration-verification codes into their own table.
     *
     * Runs after the split so an OTP emailed before the deploy is still
     * verifiable afterwards. Without this, every cashier who registered but
     * had not yet entered their code would be locked out of their own
     * account and would have to restart registration.
     */
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_tokens') || ! Schema::hasTable('users')) {
            return;
        }

        // A 6-character row in the old table is a one-time code; a 64-char one
        // is the password-reset handoff token and must stay put.
        //
        // `is_verified = false` is the other half of the test, and it is
        // required: a *verified* user asking for a password reset also has a
        // 6-character code in that same table, and that one belongs to the
        // reset flow. Unverified users are the only ones who can have both,
        // and there the ambiguity is unavoidable -- an unverified user who
        // requested a reset gets their code treated as a verification code.
        // Narrow, and the remedy is the same either way: request a new code.
        //
        // LENGTH() rather than CHAR_LENGTH() so this runs unchanged on the
        // sqlite used by the test suite. Both are byte-identical for the
        // ASCII digits in scope here.
        $candidates = DB::table('password_reset_tokens')
            ->whereIn('email', DB::table('users')->where('is_verified', false)->select('email'))
            ->whereRaw('LENGTH(token) = ?', [6])
            ->get();

        foreach ($candidates as $row) {
            DB::table('email_verification_tokens')->updateOrInsert(
                ['email' => $row->email],
                ['token' => $row->token, 'created_at' => $row->created_at],
            );

            DB::table('password_reset_tokens')->where('email', $row->email)->delete();
        }
    }

    public function down(): void
    {
        // Deliberately empty. Merging 6-digit codes back into the reset table
        // would make them indistinguishable from 64-char handoff tokens again,
        // which is the ambiguity this split exists to remove. A rollback
        // therefore leaves verification codes stranded: users mid-registration
        // re-request via /register/send-otp, which is self-service and
        // idempotent.
    }
};
