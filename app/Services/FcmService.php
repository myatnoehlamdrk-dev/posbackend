<?php

namespace App\Services;

use App\Models\FcmToken;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FcmService
{
    private ?string $projectId = null;
    private ?string $clientEmail = null;
    private ?string $privateKey = null;

    public function __construct()
    {
        $this->loadCredentials();
    }

    private function loadCredentials(): void
    {
        $path = storage_path('app/firebase-credentials.json');
        if (!file_exists($path)) {
            Log::warning('Firebase credentials file not found at ' . $path);
            return;
        }

        $data = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Invalid Firebase credentials JSON');
            return;
        }

        $this->projectId = $data['project_id'] ?? null;
        $this->clientEmail = $data['client_email'] ?? null;
        $this->privateKey = $data['private_key'] ?? null;
    }

    private function getAccessToken(): ?string
    {
        if (!$this->clientEmail || !$this->privateKey || !$this->projectId) {
            return null;
        }

        $header = base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $now = time();
        $payload = base64_encode(json_encode([
            'iss' => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signInput = $header . '.' . $payload;
        openssl_sign($signInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $signed = base64_encode($signature);
        $jwt = $signInput . '.' . Str::replace('/', '_', Str::replace('+', '-', Str::replace('=', '', $signed)));

        try {
            $client = new Client(['verify' => resource_path('certs/cacert.pem')]);
            $response = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to get FCM access token: ' . $e->getMessage());
            return null;
        }
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken || !$this->projectId) {
            return false;
        }

        try {
            $client = new Client(['verify' => resource_path('certs/cacert.pem')]);
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data),
                ],
            ];

            $response = $client->post(
                'https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload,
                ]
            );

            $statusCode = $response->getStatusCode();
            if ($statusCode === 200) {
                FcmToken::where('token', $token)->update(['last_used_at' => now(), 'active' => true]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'UNREGISTERED') || str_contains($message, 'INVALID_ARGUMENT')) {
                FcmToken::where('token', $token)->update(['active' => false]);
            }
            Log::error('Failed to send FCM message: ' . $message);
            return false;
        }
    }

    public function sendToShop(?int $shopId, string $title, string $body, array $data = []): void
    {
        if (!$shopId) {
            return;
        }

        $tokens = FcmToken::where('shop_id', $shopId)
            ->where('active', true)
            ->pluck('token');

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }
}
