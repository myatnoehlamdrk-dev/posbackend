<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Password reset: prove the mailbox, then set a new password.
 *
 * Three endpoints, two credentials. The OTP proves the requester can read mail
 * at the address on file; it is then exchanged for a longer-lived handoff
 * token, which is what actually authorises the change. Splitting those two
 * apart means the password is never changed on the strength of a six-digit code
 * that sits unread in an inbox.
 *
 * Registration verification used to live here too and now has its own
 * controller, along with its own table. The client contract for these three
 * endpoints is unchanged.
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
    ) {}

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

        $this->otp->send(
            $user->email,
            OtpService::RESET_TABLE,
            new OtpMail(OtpMail::PURPOSE_RESET),
        );

        return response()->json([
            'message' => 'OTP has been sent to your email.',
        ]);
    }

    /**
     * Trade a proven OTP for the token `resetPassword()` requires.
     *
     * `exchangeForResetToken()` spends the OTP as it issues the token, so the
     * code cannot be replayed for a second one. The token comes back in the
     * response rather than being stored server-side for the client to echo
     * later, which is what lets it stay a bearer secret with no session
     * attached.
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
            OtpService::RESET_TABLE,
        );

        $resetToken = $this->otp->exchangeForResetToken($data['email']);

        return response()->json([
            'message' => 'OTP verified successfully.',
            'reset_token' => $resetToken,
        ]);
    }

    /**
     * Set the new password.
     *
     * `confirmed` in the rules requires the client to send `password_confirmation`
     * as well; a mistyped password that then fails on the next login costs a
     * cashier their session and a support call.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->otp->assertResetTokenValid($data['email'], $data['reset_token']);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        // The whole point of a reset is that the owner no longer trusts the
        // old credential, so any session opened with it has to be destroyed
        // too. Without this an attacker who got in first simply waits out the
        // reset and stays signed in on a token nothing ever invalidated.
        $user->tokens()->delete();

        $this->otp->forget($data['email'], OtpService::RESET_TABLE);

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }
}
