<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function registerSendOtp(Request $request): JsonResponse
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

        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $data['email']],
            [
                'token' => $otp,
                'created_at' => now(),
            ]
        );

        Mail::to($data['email'])->send(new OtpMail($otp));

        return response()->json([
            'message' => 'Verification OTP has been sent to your email.',
        ]);
    }

    public function registerVerifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $resetToken) {
            throw ValidationException::withMessages([
                'otp' => ['No OTP request found for this email.'],
            ]);
        }

        if ($resetToken->token !== $data['otp']) {
            throw ValidationException::withMessages([
                'otp' => ['The OTP is incorrect.'],
            ]);
        }

        $createdAt = \Carbon\Carbon::parse($resetToken->created_at);
        if ($createdAt->diffInMinutes(now()) > 10) {
            DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->delete();

            throw ValidationException::withMessages([
                'otp' => ['The OTP has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email.'],
            ]);
        }

        $user->update(['is_verified' => true]);

        DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->delete();

        return response()->json([
            'message' => 'Email verified successfully. You can now login.',
        ]);
    }

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

        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $data['email']],
            [
                'token' => $otp,
                'created_at' => now(),
            ]
        );

        // Send OTP via Resend email
        Mail::to($data['email'])->send(new OtpMail($otp));

        return response()->json([
            'message' => 'OTP has been sent to your email.',
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $resetToken) {
            throw ValidationException::withMessages([
                'otp' => ['No OTP request found for this email.'],
            ]);
        }

        if ($resetToken->token !== $data['otp']) {
            throw ValidationException::withMessages([
                'otp' => ['The OTP is incorrect.'],
            ]);
        }

        $createdAt = \Carbon\Carbon::parse($resetToken->created_at);
        if ($createdAt->diffInMinutes(now()) > 10) {
            DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->delete();

            throw ValidationException::withMessages([
                'otp' => ['The OTP has expired. Please request a new one.'],
            ]);
        }

        // Generate a reset token for password change
        $resetTokenValue = Str::random(64);

        DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->update(['token' => $resetTokenValue, 'created_at' => now()]);

        return response()->json([
            'message' => 'OTP verified successfully.',
            'reset_token' => $resetTokenValue,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->where('token', $data['reset_token'])
            ->first();

        if (! $resetToken) {
            throw ValidationException::withMessages([
                'reset_token' => ['Invalid or expired reset token.'],
            ]);
        }

        $createdAt = \Carbon\Carbon::parse($resetToken->created_at);
        if ($createdAt->diffInMinutes(now()) > 30) {
            DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->delete();

            throw ValidationException::withMessages([
                'reset_token' => ['Reset token has expired. Please start over.'],
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->delete();

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }
}
