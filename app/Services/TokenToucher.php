<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Slide a token's expiry forward so an active session never expires on a
 * fixed schedule.
 *
 * Without this the only thing that sets `expires_at` is the login that minted
 * the token (AuthController::login), which makes the session a countdown from
 * sign-in no matter how much the till is used -- the app would drop you back
 * to the login screen exactly 5 days after you signed in, even if you had
 * been using it every hour.
 *
 * The client calls the two endpoints wired to this whenever it opens the app:
 * `GET /auth/me` (the launch-time session probe) and `GET /auth/profile`
 * (which Dashboard mounts on every foreground). Together they cover both
 * "cold start" and "till parked on the dashboard for a week".
 *
 * Two properties worth keeping:
 *
 * - It never fails the request. Extending a session is an optimisation;
 *   refusing to serve the till because the write failed would be a far worse
 *   outcome than the user being logged out a little early, so the error is
 *   logged and swallowed.
 * - It writes at most once an hour per token. This sits on a hot read path,
 *   and rewriting the same row on every request would be pointless IO for no
 *   behavioural gain.
 */
class TokenToucher
{
    /**
     * Skip the write unless it would push expiry at least this far out.
     *
     * Bounds this to one UPDATE per token per hour while keeping the stored
     * `expires_at` never more than an hour behind the intended window.
     */
    public const MIN_STEP_MINUTES = 60;

    public static function touch(Request $request): void
    {
        try {
            $token = $request->user()?->currentAccessToken();

            if ($token === null || $token->expires_at === null) {
                // A token with no expiry of its own has nothing to slide; it
                // is governed solely by sanctum's age cap. Every token this
                // app mints sets one, so this only guards a token minted
                // elsewhere (e.g. a `php artisan tinker` session).
                return;
            }

            $target = now()->addMinutes((int) config('security.token_ttl_minutes'));

            if ($token->expires_at->greaterThan($target->copy()->subMinutes(self::MIN_STEP_MINUTES))) {
                return;
            }

            $token->update(['expires_at' => $target]);
        } catch (\Throwable $e) {
            Log::warning('Could not slide API token expiry; session will expire earlier than intended.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
