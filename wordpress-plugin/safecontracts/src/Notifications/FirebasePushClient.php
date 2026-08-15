<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class FirebasePushClient
{
    private mixed $accessTokenResolver;
    private mixed $httpPost;

    public function __construct(
        private ?FirebaseSettingsService $settings = null,
        mixed $accessTokenResolver = null,
        mixed $httpPost = null
    ) {
        $this->settings ??= new FirebaseSettingsService();
        $this->accessTokenResolver = $accessTokenResolver ?? static fn (): string => (new FirebaseAccessTokenProvider())->accessToken();
        $this->httpPost = $httpPost ?? static fn (string $url, array $args): mixed => wp_remote_post($url, $args);
    }

    /**
     * @param array<string, mixed> $device
     * @param array<string, mixed> $data
     * @return array{success:bool,retryable:bool,permanent_token_error:bool,http_status:int,error_code:string,error_message:string,message_id:string}
     */
    public function send(array $device, string $title, string $body, array $data): array
    {
        $token = trim((string) ($device['token'] ?? ''));
        if (strlen($token) < 20 || strlen($token) > 4096 || preg_match('/\s/', $token)) {
            throw new InvalidArgumentException('Firebase target token is invalid.');
        }
        $title = $this->boundedText($title, 255, 'notification title');
        $body = $this->boundedText($body, 4000, 'notification body');
        $safeData = $this->normalizeData($data);
        $account = $this->settings->serverCredentialsForDelivery();
        $projectId = $account['project_id'];
        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';

        try {
            $accessToken = (string) ($this->accessTokenResolver)();
            if ($accessToken === '') {
                throw new RuntimeException('Firebase access token is unavailable.');
            }
            $payload = json_encode([
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $safeData,
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (! is_string($payload)) {
                throw new RuntimeException('Firebase message payload could not be encoded.');
            }
            $response = ($this->httpPost)($url, [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'body' => $payload,
            ]);
            if (function_exists('is_wp_error') && is_wp_error($response)) {
                return $this->failure(0, 'transport_error', 'Firebase transport request failed.', true, false);
            }
            $status = function_exists('wp_remote_retrieve_response_code') ? (int) wp_remote_retrieve_response_code($response) : (int) ($response['response']['code'] ?? 0);
            $responseBody = function_exists('wp_remote_retrieve_body') ? (string) wp_remote_retrieve_body($response) : (string) ($response['body'] ?? '');
            $decoded = json_decode($responseBody, true);
            if ($status >= 200 && $status < 300) {
                return [
                    'success' => true,
                    'retryable' => false,
                    'permanent_token_error' => false,
                    'http_status' => $status,
                    'error_code' => '',
                    'error_message' => '',
                    'message_id' => is_array($decoded) ? substr((string) ($decoded['name'] ?? ''), 0, 255) : '',
                ];
            }

            [$errorCode, $errorMessage] = $this->parseError($decoded, $status);
            $permanentToken = in_array($errorCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true);
            $retryable = in_array($status, [408, 429, 500, 502, 503, 504], true)
                || in_array($errorCode, ['UNAVAILABLE', 'INTERNAL', 'RESOURCE_EXHAUSTED'], true);
            return $this->failure($status, $errorCode, $errorMessage, $retryable, $permanentToken);
        } catch (Throwable $error) {
            return $this->failure(0, 'transport_exception', 'Firebase push delivery failed before a provider response.', true, false);
        }
    }

    /** @param array<string, mixed> $decoded @return array{0:string,1:string} */
    private function parseError(mixed $decoded, int $status): array
    {
        $code = 'HTTP_' . $status;
        $message = 'Firebase push delivery failed.';
        if (! is_array($decoded)) {
            return [$code, $message];
        }
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $statusCode = strtoupper(trim((string) ($error['status'] ?? '')));
        if ($statusCode !== '') {
            $code = $statusCode;
        }
        foreach (($error['details'] ?? []) as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $fcmCode = strtoupper(trim((string) ($detail['errorCode'] ?? '')));
            if ($fcmCode !== '') {
                $code = $fcmCode;
                break;
            }
        }
        $providerMessage = trim(strip_tags((string) ($error['message'] ?? '')));
        if ($providerMessage !== '') {
            $message = substr($providerMessage, 0, 1000);
        }
        return [$code, $message];
    }

    /** @return array{success:bool,retryable:bool,permanent_token_error:bool,http_status:int,error_code:string,error_message:string,message_id:string} */
    private function failure(int $status, string $code, string $message, bool $retryable, bool $permanentToken): array
    {
        return [
            'success' => false,
            'retryable' => $retryable,
            'permanent_token_error' => $permanentToken,
            'http_status' => max(0, $status),
            'error_code' => substr($code, 0, 100),
            'error_message' => substr(trim(strip_tags($message)), 0, 1000),
            'message_id' => '',
        ];
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function normalizeData(array $data): array
    {
        $safe = [];
        if (count($data) > 30) {
            throw new InvalidArgumentException('Firebase notification data contains too many fields.');
        }
        foreach ($data as $key => $value) {
            $name = (string) $key;
            if (! preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $name)) {
                throw new InvalidArgumentException('Firebase notification data key is invalid.');
            }
            if (is_array($value) || is_object($value) || is_resource($value)) {
                throw new InvalidArgumentException('Firebase notification data values must be scalar.');
            }
            $text = trim((string) $value);
            if (strlen($text) > 1000) {
                throw new InvalidArgumentException('Firebase notification data value is too long.');
            }
            $safe[$name] = $text;
        }
        return $safe;
    }

    private function boundedText(string $value, int $maxLength, string $field): string
    {
        $text = trim(strip_tags($value));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new InvalidArgumentException('Firebase ' . $field . ' is invalid.');
        }
        return $text;
    }
}
