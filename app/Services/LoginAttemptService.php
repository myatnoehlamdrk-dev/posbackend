<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;

class LoginAttemptService
{
    /**
     * Append one row to the login log.
     *
     * Recorded for every attempt, successful or not, and for emails that match
     * no account -- those are the attempts worth noticing, and they are the
     * ones a "log the failures for known users" approach throws away.
     */
    public function record(
        Request $request,
        ?User $user,
        string $email,
        bool $successful,
        ?string $failureReason = null,
        ?string $deviceName = null,
    ): void {
        LoginAttempt::create([
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'device_name' => $deviceName,
            'successful' => $successful,
            'failure_reason' => $failureReason,
        ]);
    }

    /**
     * Count a failed attempt and lock the account once the threshold is hit.
     *
     * Silently ignored for unknown emails: there is no row to lock, and
     * inventing one on a failed guess would let anyone mass-create users.
     */
    public function registerFailure(?User $user): void
    {
        if (! $user) {
            return;
        }

        // The previous lock has expired, so this attempt starts a fresh
        // sequence rather than re-locking on the first mistake.
        if ($user->locked_until !== null && $user->locked_until->isPast()) {
            $user->failed_attempts = 0;
            $user->locked_until = null;
        }

        $user->failed_attempts = (int) $user->failed_attempts + 1;

        if ($user->failed_attempts >= (int) config('security.login.max_attempts')) {
            $user->locked_until = now()->addMinutes((int) config('security.login.lockout_minutes'));
        }

        $user->save();
    }

    /** Clear the failure counter after a successful sign-in. */
    public function registerSuccess(User $user, Request $request): void
    {
        $user->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
    }
}