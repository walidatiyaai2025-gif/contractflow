<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use RuntimeException;
use Throwable;
use WP_Error;

final class FirebaseAccessTokenProvider
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    /** @var array<string, array{token:string,expires_at:int}> */
    private static array $cache = [];

    public static function register(): void
    {
        add_filter('safecontracts_firebase_access_token', [self::class, 'provide'], 10, 3);
    }

    public static function provide(mixed $current, string $credentialReference, string $projectId): mixed
    {
        if (is_string($current) && trim($current) !== '') {
            return $current;
        }
        if ($credentialReference !== FirebaseServiceAccountVault::REFERENCE) {
            return $current;
        }

        try {
            return (new self())->accessToken($projectId);
        } catch (Throwable $error) {
            unset($error);
            do_action('safecontracts_firebase_auth_failed', 'firebase_auth_unavailable');
            return '';
        }
    }

    public function accessToken(string $projectId): string
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            throw new RuntimeException('Firebase project ID is required.');
        }
        $cached = self::$cache[$projectId] ?? null;
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60) {
            return (string) $cached['token'];
        }

        $credential = (new FirebaseServiceAccountVault())->credential($projectId);
        $assertion = $this->buildJwtAssertion($credential);
        $response = wp_remote_post($credential['token_uri'], [
            'timeout' => 12,
            'redirection' => 0,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => self::GRANT_TYPE,
                'assertion' => $assertion,
            ],
        ]);
        if ($response instanceof WP_Error || is_wp_error($response)) {
            throw new RuntimeException('Firebase OAuth transport failed.');
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300 || ! is_array($decoded)) {
            throw new RuntimeException('Firebase OAuth authentication failed.');
        }
        $token = trim((string) ($decoded['access_token'] ?? ''));
        $expiresIn = (int) ($decoded['expires_in'] ?? 0);
        if ($token === '' || strlen($token) > 8192 || $expiresIn < 60 || $expiresIn > 7200) {
            throw new RuntimeException('Firebase OAuth response is invalid.');
        }

        self::$cache[$projectId] = [
            'token' => $token,
            'expires_at' => time() + $expiresIn,
        ];
        return $token;
    }

    /** @return array{success:bool,status_code:int,error_code:?string} */
    public function testConnection(string $projectId): array
    {
        try {
            $token = $this->accessToken($projectId);
        } catch (Throwable $error) {
            unset($error);
            return ['success' => false, 'status_code' => 0, 'error_code' => 'firebase_auth_unavailable'];
        }

        $body = wp_json_encode([
            'validate_only' => true,
            'message' => [
                'topic' => 'safecontracts-health',
                'notification' => [
                    'title' => 'SafeContracts Firebase test',
                    'body' => 'Credential and FCM authorization validation.',
                ],
            ],
        ]);
        if (! is_string($body) || $body === '') {
            return ['success' => false, 'status_code' => 0, 'error_code' => 'firebase_request_invalid'];
        }

        $response = wp_remote_post(
            'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send',
            [
                'timeout' => 15,
                'redirection' => 0,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'body' => $body,
            ]
        );
        if ($response instanceof WP_Error || is_wp_error($response)) {
            return ['success' => false, 'status_code' => 0, 'error_code' => 'firebase_transport_error'];
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'status_code' => $status, 'error_code' => null];
        }
        return [
            'success' => false,
            'status_code' => $status,
            'error_code' => 'firebase_http_' . $status,
        ];
    }

    /**
     * @param array{type:string,project_id:string,private_key_id:string,private_key:string,client_email:string,token_uri:string} $credential
     */
    private function buildJwtAssertion(array $credential): string
    {
        $now = time();
        $header = $this->base64UrlEncode((string) wp_json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $credential['private_key_id'],
        ]));
        $claims = $this->base64UrlEncode((string) wp_json_encode([
            'iss' => $credential['client_email'],
            'scope' => self::SCOPE,
            'aud' => $credential['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $header . '.' . $claims;
        $signature = '';
        $signed = openssl_sign($unsigned, $signature, $credential['private_key'], OPENSSL_ALGO_SHA256);
        if (! $signed || $signature === '') {
            throw new RuntimeException('Firebase OAuth assertion signing failed.');
        }
        return $unsigned . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
