<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\ShopResource;
use App\Http\Resources\UserResource;
use App\Mail\OtpMail;
use App\Models\Shop;
use App\Models\User;
use App\Services\ImgBBService;
use App\Services\LoginAttemptService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected LoginAttemptService $loginAttempts,
        protected OtpService $otp,
        protected ImgBBService $imgbb,
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

            // A registrant has no token yet, so the shop cannot be created
            // through POST /shops -- that route sits behind auth:sanctum and
            // 401s every first-time sign-up. The shop therefore travels with
            // the account and is written in the same transaction as the user,
            // which also keeps a shop from existing without its owner if the
            // user insert fails. `shopId` stays valid for the other order:
            // picking a shop that already exists.
            'shop' => ['nullable', 'array'],
            'shop.name' => ['required_with:shop', 'string', 'max:255'],
            'shop.type' => ['nullable', 'string', 'max:255'],
            'shop.physicalAddress' => ['nullable', 'string', 'max:255'],
            'shop.logoUrl' => ['nullable', 'string', 'max:1024'],
            'shop.logoData' => ['nullable', 'string'],
            'shop.ownerInformation' => ['nullable', 'array'],
            'shop.ownerInformation.name' => ['nullable', 'string', 'max:255'],
            'shop.ownerInformation.email' => ['nullable', 'email', 'max:255'],
            'shop.ownerInformation.phone' => ['nullable', 'string', 'max:50'],
        ]);

        // `role` is deliberately absent from the validated payload. It used to
        // be accepted from the request and written straight through, which let
        // anyone POST `{"role": "admin"}` at this endpoint and walk into the
        // admin group on their first sign-in. Roles are assigned by an admin
        // through the admin endpoints, never by the registrant.

        // The logo is resolved before the transaction opens: uploading to ImgBB
        // is an outbound HTTP call, and holding a write transaction across it
        // would keep the users and shops tables locked for the length of the
        // round trip -- or for its timeout, if ImgBB is slow.
        $shopLogo = ($data['shopId'] ?? null) === null && isset($data['shop'])
            ? $this->resolveShopLogo($data['shop'])
            : null;

        [$user, $shop] = DB::transaction(function () use ($data, $shopLogo) {
            $shop = null;
            $shopId = $data['shopId'] ?? null;

            if ($shopId === null && isset($data['shop'])) {
                $shop = Shop::create([
                    'shop_image' => $shopLogo,
                    'shop_name' => $data['shop']['name'],
                    'shop_type' => $data['shop']['type'] ?? null,
                    'shop_physical_address' => $data['shop']['physicalAddress'] ?? null,
                    'owner_name' => $data['shop']['ownerInformation']['name'] ?? null,
                    'owner_email' => $data['shop']['ownerInformation']['email'] ?? null,
                    'owner_phone' => $data['shop']['ownerInformation']['phone'] ?? null,
                ]);
                $shopId = $shop->id;
            }

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
                'shop_id' => $shopId,
                'is_verified' => false,
            ]);

            return [$user, $shop];
        });

        // Verification code, stored in its own table and confirmed by
        // EmailVerificationController. Previously written to
        // `password_reset_tokens` alongside password-reset codes, so the two
        // flows for one address overwrote each other. Deliberately outside the
        // transaction: a mail server that cannot be reached must not roll back
        // an account that was otherwise created.
        $this->otp->send(
            $user->email,
            OtpService::VERIFICATION_TABLE,
            new OtpMail(OtpMail::PURPOSE_VERIFICATION),
        );

        return response()->json([
            'message' => 'Registration successful. Please verify your email.',
            'email' => $data['email'],
            'id' => (string) $user->id,
            'fullName' => $user->name,
            'shopId' => $user->shop_id !== null ? (string) $user->shop_id : null,
            'shop' => $shop !== null ? new ShopResource($shop) : null,
        ], 201);
    }

    /**
     * Resolves the shop image from a registration payload.
     *
     * The logo reaches this endpoint as raw base64 because the authenticated
     * `/images` upload cannot be called before the account exists, so this does
     * server-side what that endpoint does: hand the bytes to ImgBB and store
     * the link it returns, which is how every other image in the app is saved.
     *
     * ImgBB being unreachable must not fail a sign-up, so a failed upload falls
     * back to writing the file locally -- the same fallback
     * `ImageController::store` uses, and the same `/uploads/...` path
     * `resolveMediaUrl` on the client understands.
     */
    private function resolveShopLogo(array $shop): ?string
    {
        $encoded = $shop['logoData'] ?? null;
        if (! is_string($encoded) || $encoded === '') {
            return $shop['logoUrl'] ?? null;
        }

        try {
            $url = $this->imgbb->upload($encoded, 'shop_logo')['url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }

            Log::warning('ImgBB returned no url for the registration logo; falling back to local storage.');
        } catch (\Throwable $e) {
            Log::warning('Registration logo upload to ImgBB failed; falling back to local storage', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->storeLogoLocally($encoded) ?? $shop['logoUrl'] ?? null;
    }

    /**
     * Writes the decoded logo to `public/uploads` and returns its local path.
     *
     * Returns null rather than throwing when the payload is not usable base64:
     * a logo that cannot be read is not a reason to reject the account.
     */
    private function storeLogoLocally(string $encoded): ?string
    {
        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '') {
            Log::warning('Registration logo was not valid base64; saving the shop without it.');

            return null;
        }

        $extension = match (true) {
            str_starts_with($binary, "\xFF\xD8") => 'jpg',
            str_starts_with($binary, "\x89PNG") => 'png',
            str_starts_with($binary, 'GIF8') => 'gif',
            str_starts_with($binary, 'RIFF') => 'webp',
            default => 'png',
        };

        $directory = public_path('uploads');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = uniqid('shop_', true).'.'.$extension;
        file_put_contents($directory.DIRECTORY_SEPARATOR.$filename, $binary);

        return '/uploads/'.$filename;
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
