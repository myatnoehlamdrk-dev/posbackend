<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        // Sanctum::currentRequestHost(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired, and it applies to every Sanctum token this app
    | issues. It was null, which made a leaked token valid forever from any
    | machine, and no amount of client-side cleanup could take it back.
    |
    | Sanctum does not use this as a session length. Its guard tests
    | `created_at`, not `expires_at`:
    |
    |     $accessToken->created_at->gt(now()->subMinutes($this->expiration))
    |
    | (vendor/laravel/sanctum/src/Guard.php). It is therefore a hard age cap on
    | how long any one token may exist, regardless of how recently it was used,
    | and it must sit well above the sliding idle window or it would sign an
    | actively used till out on a fixed schedule.
    |
    | The 5-day idle window this app actually enforces lives on the token's own
    | `expires_at`, which `security.token_ttl_minutes` seeds at login and
    | `App\Services\TokenToucher` pushes forward on each authenticated read.
    | This backstop only matters for a token whose `expires_at` is missing or
    | was somehow set far ahead -- every token this app mints sets it
    | (AuthController::login) -- so it is a last resort, not a session length.
    |
    | 129600 minutes = 90 days.
    |
    */

    'expiration' => 129600,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
