<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use RuntimeException;

final class FirebaseAccessTokenProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const AUDIENCE = 'https://oauth2.googleapis.com/token';

    public function __construct(private ?FirebaseSettingsService $settings = null)
    {
        $this->settings ??= new FirebaseSettingsService();
    }

    public function accessToken(): string
    {
        $account = $this->settings->serverCredentialsForDelivery();
        $cacheKey = 'safecontracts_fcm_oauth_' . substr(hash('sha256', $account['project_id'] . '|' . $account['client_email']), 0, 32);
        if (function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $now = time();
        $jwt = $this->jwt($account, $now);
        if (! function_exists('wp_remote_post')) {
            throw new RuntimeException('WordPress HTTP API is required for Firebase OAuth.');
        }
        $response = wp_remote_post($account['token_uri'], [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);
        if (function_exists('is_wp_error') && is_wp_error($response)) {
            throw new RuntimeException('Firebase OAuth request failed.');
        }
        $status = function_exists('wp_remote_retrieve_response_code') ? (int) wp_remote_retrieve_response_code($response) : 0;
        $body = function_exists('wp_remote_retrieve_body') ? (string) wp_remote_retrieve_body($response) : '';
        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300 || ! is_array($decoded) || ! isset($decoded['access_token'])) {
            throw new RuntimeException('Firebase OAuth token exchange failed with HTTP ' . $status . '.');
        }
        $token = trim((string) $decoded['access_token']);
        if ($token === '' || strlen($token) > 8192) {
            throw new RuntimeException('Firebase OAuth returned an invalid access token.');
        }
        $expires = max(60, min(3600, (int) ($decoded['expires_in'] ?? 3600)) - 120);
        if (function_exists('set_transient')) {
            set_transient($cacheKey, $token, $expires);
        }
        return $token;
    }

    /** @param array<string, string> $account */
    private function jwt(array $account, int $now): string
    {
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES) ?: '{}');
        $claims = $this->base64Url(json_encode([
            'iss' => $account['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_UNESCAPED_SLASHES) ?: '{}');
        $unsigned = $header . '.' . $claims;

        if (! function_exists('openssl_pkey_get_private') || ! function_exists('openssl_sign')) {
            throw new RuntimeException('OpenSSL is required for Firebase service-account authentication.');
        }
        $key = openssl_pkey_get_private($account['private_key']);
        if ($key === false) {
            throw new RuntimeException('Firebase service-account private key could not be loaded.');
        }
        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256);
        if (is_resource($key)) {
            openssl_free_key($key);
        }
        if (! $ok) {
            throw new RuntimeException('Firebase service-account assertion could not be signed.');
        }
        return $unsigned . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
