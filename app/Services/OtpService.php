<?php

namespace App\Services;

use App\Mail\OtpMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Storage and verification for one-time codes.
 *
 * Both flows that need a mailed code -- proving an address at registration,
 * and authorising a password reset -- used to be inline in
 * PasswordResetController, writing into one shared table. They now keep
 * separate tables and call in here, so the storage rules below are stated once
 * instead of drifting between copies.
 *
 * The table is a parameter rather than inferred from the caller because the two
 * flows are not interchangeable: a verification code and a reset code for the
 * same address must be able to coexist, and a caller that picked the wrong
 * table would silently read the other flow's code as valid. Naming the table
 * at every call site keeps that decision visible.
 */
class OtpService
{
    /** Registration verification. Superseded the shared table's 6-char rows. */
    public const VERIFICATION_TABLE = 'email_verification_tokens';

    /** Password reset. Also holds the 64-char handoff token after verify. */
    public const RESET_TABLE = 'password_reset_tokens';

    /** Length of the one-time code itself. Distinct from the handoff token. */
    private const OTP_LENGTH = 6;

    /** Largest six-digit value, inclusive. */
    private const OTP_MAX = 999999;

    /** Length of the reset handoff token minted once an OTP is proven. */
    private const RESET_TOKEN_LENGTH = 64;

    /**
     * Mint a code, store it against the email, and mail it.
     *
     * `updateOrInsert` rather than insert: one live code per address, and a
     * re-request replaces the old one. That is the intended behaviour for both
     * flows -- a second request should invalidate the first -- and it is why
     * the table has no history worth keeping. Guessing at a superseded code
     * therefore fails against the newest one, which is what the user actually
     * has in hand.
     */
    public function send(string $email, string $table, OtpMail $mail): void
    {
        $otp = $this->generateOtp();

        DB::table($table)->updateOrInsert(
            ['email' => $email],
            ['token' => $otp, 'created_at' => now()],
        );

        $mail->withCode($otp);
        Mail::to($email)->send($mail);
    }

    /**
     * Check a submitted code against the stored one, consuming it on success.
     *
     * Consumes on success only. A wrong code must leave the stored value
     * intact, otherwise one typo would force the user to restart the flow, and
     * an attacker could invalidate a victim's code by guessing at it.
     *
     * Deletes the row on expiry rather than leaving it to be re-checked: an
     * expired code that lingers is indistinguishable from a live one to anyone
     * reading the table, and the next send overwrites it regardless.
     *
     * @throws ValidationException
     */
    public function verify(string $email, string $otp, string $table): void
    {
        $row = DB::table($table)->where('email', $email)->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'otp' => ['No OTP request found for this email.'],
            ]);
        }

        if (! hash_equals((string) $row->token, (string) $otp)) {
            throw ValidationException::withMessages([
                'otp' => ['The OTP is incorrect.'],
            ]);
        }

        if ($this->isExpired($row->created_at, (int) config('security.otp.ttl_minutes'))) {
            DB::table($table)->where('email', $email)->delete();

            throw ValidationException::withMessages([
                'otp' => ['The OTP has expired. Please request a new one.'],
            ]);
        }

        DB::table($table)->where('email', $email)->delete();
    }

    /**
     * Trade a verified reset OTP for a long-lived handoff token.
     *
     * `verify()` deletes the row it just accepted, so this re-writes it. Same
     * column, same key, different value: a 6-character code proves mailbox
     * access, a 64-character token authorises the password change. Keeping them
     * in one row means the OTP is spent the instant the token is issued, so a
     * code captured from an email cannot be replayed to fetch a second token.
     *
     * The timestamp is rewritten too, which restarts the clock on the longer
     * `reset_token_ttl_minutes`. Without that the token would inherit the
     * remainder of the OTP's ten minutes and expire almost immediately.
     */
    public function exchangeForResetToken(string $email): string
    {
        $token = $this->generateResetToken();

        DB::table(self::RESET_TABLE)->updateOrInsert(
            ['email' => $email],
            ['token' => $token, 'created_at' => now()],
        );

        return $token;
    }

    /**
     * Check the handoff token issued by `exchangeForResetToken()`.
     *
     * Separate from `verify()` because the expiry differs and because this one
     * must not consume the row on success -- `resetPassword()` needs to read it
     * once more to decide whether to honour the request.
     *
     * `hash_equals` throughout: these are bearer secrets, and a plain `===`
     * leaks the match position through timing.
     *
     * @throws ValidationException
     */
    public function assertResetTokenValid(string $email, string $token): void
    {
        $row = DB::table(self::RESET_TABLE)
            ->where('email', $email)
            ->where('token', $token)
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'reset_token' => ['Invalid or expired reset token.'],
            ]);
        }

        if ($this->isExpired($row->created_at, (int) config('security.reset_token_ttl_minutes'))) {
            DB::table(self::RESET_TABLE)->where('email', $email)->delete();

            throw ValidationException::withMessages([
                'reset_token' => ['Reset token has expired. Please start over.'],
            ]);
        }
    }

    public function forget(string $email, string $table): void
    {
        DB::table($table)->where('email', $email)->delete();
    }

    /**
     * Cryptographically random digit string for the one-time code.
     *
     * `random_int()` is the CSPRNG; `rand()` and `mt_rand()` are not, and a
     * predictable code is a guessable one. `str_pad` covers the low end of the
     * range so the result is always six characters -- a 5-digit code would
     * otherwise slip through the `size:6` validation as a malformed request
     * rather than an incorrect code.
     */
    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, self::OTP_MAX), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * The handoff token, which is not a code and must not look like one.
     *
     * `random_int()` over a 64-digit range is not usable here: `10 ** 64`
     * overflows to a float, which `random_int()` rejects outright. Hence
     * `Str::random()`, which draws from the CSPRNG as well and draws from the
     * full byte alphabet rather than just digits. The wider alphabet is the
     * point -- this value is never typed by a human, only compared.
     */
    private function generateResetToken(): string
    {
        return Str::random(self::RESET_TOKEN_LENGTH);
    }

    private function isExpired(mixed $createdAt, int $ttlMinutes): bool
    {
        if ($createdAt === null) {
            return true;
        }

        return Carbon::parse($createdAt)->diffInMinutes(now()) > $ttlMinutes;
    }
}
