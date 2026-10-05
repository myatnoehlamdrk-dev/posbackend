<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login Throttling
    |--------------------------------------------------------------------------
    |
    | Per-IP throttling alone does not stop credential stuffing: an attacker
    | who rotates addresses never trips it, and a slow drip never exceeds the
    | limit. These two settings work per account, so a single host cannot lock
    | out a whole shop by hammering one address.
    |
    | After `max_attempts` consecutive failures the account is locked for
    | `lockout_minutes`. A successful login clears the counter, as does the
    | first attempt made after a lock has expired -- otherwise the counter
    | would still be over the threshold and the very next mistake would lock
    | the account again immediately.
    |
    */

    'login' => [
        'max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => env('LOGIN_LOCKOUT_MINUTES', 15),
        'per_minute' => env('LOGIN_RATE_LIMIT_PER_MINUTE', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Minutes before a Sanctum token issued at login stops being accepted.
    |
    | Sanctum treats the global `sanctum.expiration` and a token's own
    | `expires_at` as two independent conditions that must both hold, so this
    | is the effective cap when it is the shorter of the two. The value below
    | is deliberately shorter than the 24-hour backstop in `sanctum.php`:
    | a till that is unattended overnight should need a sign-in the next
    | morning, and a token leaked from a stolen handset should stop working
    | long before the day is out.
    |
    */

    'token_ttl_minutes' => env('API_TOKEN_TTL_MINUTES', 720),

    /*
    |--------------------------------------------------------------------------
    | One-Time Codes
    |--------------------------------------------------------------------------
    |
    | Both the registration-verification code and the password-reset code are
    | 6 digits, mailed to the address on file. They live in separate tables now
    | (see the email_verification_tokens migration) but expire under the same
    | rule, so one setting covers both. Ten minutes is short enough that a
    | code left in an inbox is worthless by the time anyone reads it.
    |
    */

    'otp' => [
        'ttl_minutes' => env('OTP_TTL_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Handoff Token
    |--------------------------------------------------------------------------
    |
    | Longer than the OTP by design, and deliberately not a continuation of it.
    | Verifying the code mints a fresh 64-character token and restarts the
    | clock, so the OTP's ten minutes do not eat into the window in which the
    | reset can actually be completed. Thirty minutes is the shortest that
    | survives typing a new password on a phone keyboard.
    |
    */

    'reset_token_ttl_minutes' => env('RESET_TOKEN_TTL_MINUTES', 30),

];