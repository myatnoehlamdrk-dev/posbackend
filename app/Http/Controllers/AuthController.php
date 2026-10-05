<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Services\LoginAttemptService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected LoginAttemptService $loginAttempts,
        protected OtpService $otp,
    ) {}
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string'],
            'social' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'nrc' => ['nullable', 'string'],
            'billingWay' => ['nullable', 'max:255'],
            'dob' => ['nullable', 'string'],
            'gender' => ['nullable', 'string'],
            'shopId' => ['nullable', 'exists:shops,id'],
        ]);

        // `role` is deliberately absent from the validated payload. It used to
        // be accepted from the request and written straight through, which let
        // anyone POST `{"role": "admin"}` at this endpoint and walk into the
        // admin group on their first sign-in. Roles are assigned by an admin
        // through the admin endpoints, never by the registrant.
        $user = User::create([
            'name' => $data['fullName'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'social' => $data['social'] ?? null,
            'role' => UserRole::CASHIER->value,
            'address' => $data['address'] ?? null,
            'nrc_no' => $data['nrc'] ?? null,
            'billing_way' => $data['billingWay'] ?? null,
            'date_of_birth' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'shop_id' => $data['shopId'] ?? null,
            'is_verified' => false,
        ]);

        // Verification code, stored in its own table and confirmed by
        // EmailVerificationController. Previously written to
        // `password_reset_tokens` alongside password-reset codes, so the two
        // flows for one address overwrote each other.
        $this->otp->send(
            $user->email,
            OtpService::VERIFICATION_TABLE,
            new OtpMail(OtpMail::PURPOSE_VERIFICATION),
        );

        return response()->json([
            'message' => 'Registration successful. Please verify your email.',
            'email' => $data['email'],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            // Names the session so an admin can see which till is signed in and
            // revoke one device without touching the rest.
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $deviceName = $data['device_name'] ?? 'mobile';
        $user = User::where('email', $data['email'])->first();

        // Checked before the password so a locked account cannot be used to
        // work through the remaining guesses. The message names the account
        // as locked, which does confirm it exists -- but `forgot-password` and
        // `register/send-otp` already answer "no such email" plainly, so
        // hiding it here would cost the cashier a support call and buy nothing.
        if ($user && $user->isLockedOut()) {
            $this->loginAttempts->record($request, $user, $data['email'], false, 'locked_out', $deviceName);

            throw ValidationException::withMessages([
                'email' => ["Too many failed attempts. Try again in {$user->lockoutMinutesRemaining()} minute(s)."],
            ]);
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->loginAttempts->registerFailure($user);
            $this->loginAttempts->record($request, $user, $data['email'], false, 'bad_credentials', $deviceName);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_verified) {
            $this->loginAttempts->record($request, $user, $data['email'], false, 'unverified', $deviceName);

            throw ValidationException::withMessages([
                'email' => ['Please verify your email first. Check your inbox for the verification code.'],
            ]);
        }

        if (! $user->active_status) {
            // Treated exactly like a deactivation: the tokens issued before
            // this moment must die with the account, otherwise a dismissed
            // cashier keeps a working token until it expires on its own.
            if ($user->tokens()->exists()) {
                $user->tokens()->delete();
            }

            $this->loginAttempts->record($request, $user, $data['email'], false, 'inactive', $deviceName);

            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact administrator for access.'],
            ]);
        }

        $this->loginAttempts->registerSuccess($user, $request);

        $token = $user->createToken(
            $deviceName,
            // `*` keeps every existing endpoint working while making the
            // mechanism explicit. Narrowing this to per-role abilities is the
            // next step, and it needs a migration plan for tokens already
            // issued, because a token minted without the ability would start
            // failing `can()` checks the moment any policy started reading it.
            ['*'],
            now()->addMinutes((int) config('security.token_ttl_minutes')),
        )->plainTextToken;

        $this->loginAttempts->record($request, $user, $data['email'], true, null, $deviceName);

        return response()->json(array_merge(UserResource::make($user)->resolve(request()), [
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Revoke every session for the signed-in user, on every device.
     *
     * The ordinary logout only ends the session that made the request, which
     * is the right default but leaves a stolen handset signed in.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Signed out of all devices.']);
    }

    /**
     * The caller's live sessions, newest first.
     *
     * `name` is the device label supplied at login and `last_used_at` is
     * Sanctum's own tracking, so this answers "which till is currently
     * signed in to this account" without any extra bookkeeping.
     */
    public function sessions(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->tokens()
                ->latest('last_used_at')
                ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at', 'expires_at'])
        );
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(UserResource::make($request->user()->load('shop')));
    }
}
