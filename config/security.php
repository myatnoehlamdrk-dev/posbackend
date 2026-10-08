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
    | Minutes a Sanctum token issued at login survives without being touched.
    |
    | Sanctum's global `sanctum.expiration` is checked against the token's
    | `created_at` (an age cap, not a session length), so this is the only
    | value that decides when an unused session dies: the guard reads the
    | token's own `expires_at` separately and requires both to hold.
    |
    | The session is sliding rather than fixed: `App\Services\TokenToucher`
    | moves `expires_at` forward to now + 5 days on the authenticated reads
    | the client makes when it opens the app, so a till in daily use stays
    | signed in indefinitely while one left alone for 5 days signs itself
    | out. A token minted here is valid for 5 days from the moment of login
    | if nobody opens the app again.
    |
    | This deliberately trades the old 12-hour hard stop -- which forced a
    | sign-in every morning and limited a stolen token to half a day -- for a
    | 5-day idle window. Lowering this shortens both the idle window and the
    | grace a freshly logged-in device gets before it must be opened once.
    |
    */

    'token_ttl_minutes' => env('API_TOKEN_TTL_MINUTES', 7200),

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