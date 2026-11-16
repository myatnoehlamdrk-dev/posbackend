<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Email verification for a newly registered account.
 *
 * Split out of PasswordResetController, which used to own these two methods
 * because both flows happened to write into `password_reset_tokens`. They no
 * longer share a table, and keeping them here meant a reader looking for the
 * password-reset flow had to skip past half a file of registration logic.
 *
 * The client contract is unchanged: same URIs, same request bodies, same
 * response shapes and error keys. That matters more than tidiness in a POS
 * rollout -- an installed terminal cannot be pushed an update, so a response
 * that changes shape here breaks every till on the old build.
 */
class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
    ) {}

    /**
     * Send a code to an address that has not been confirmed yet.
     *
     * Responds 200 with a message when the address is already verified, rather
     * than 422. Re-requesting is something a cashier does by reflex after
     * switching email clients, and failing that request would read as a broken
     * feature. Sending nothing is the important part: a working address here
     * would let anyone probe which emails hold accounts.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email address.'],
            ]);
        }

        if ($user->is_verified) {
            return response()->json([
                'message' => 'Account is already verified. Please login.',
            ]);
        }

        $this->sendOrFail(
            $user->email,
            OtpService::VERIFICATION_TABLE,
            new OtpMail(OtpMail::PURPOSE_VERIFICATION),
            'Verification OTP failed to send.',
        );

        return response()->json([
            'message' => 'Verification OTP has been sent to your email.',
        ]);
    }

    /**
     * Mail the code, or answer 503 instead of letting the transport exception
     * surface as an opaque 500.
     *
     * A 500 here is indistinguishable from a broken server, so the cashier
     * retries the whole flow. The body carries a sentence the client already
     * renders, and the provider's reason is logged for us.
     */
    private function sendOrFail(string $email, string $table, OtpMail $mail, string $logMessage): void
    {
        try {
            $this->otp->send($email, $table, $mail);
        } catch (\Throwable $e) {
            Log::error($logMessage, [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw new HttpResponseException(response()->json([
                'message' => 'Could not send the email right now. Please try again in a moment.',
            ], 503));
        }
    }

    /**
     * Confirm the address and allow the account to sign in.
     *
     * The code is verified before the account is touched, so a submission for
     * an address with no pending code fails on the code rather than quietly
     * reporting a verified user that was never loaded.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $this->otp->verify(
            $data['email'],
            $data['otp'],
            OtpService::VERIFICATION_TABLE,
        );

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email.'],
            ]);
        }

        $user->update(['is_verified' => true]);

        return response()->json([
            'message' => 'Email verified successfully. You can now login.',
        ]);
    }
}
