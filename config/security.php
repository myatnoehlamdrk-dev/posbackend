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

];