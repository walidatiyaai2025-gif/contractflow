<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class FirebasePushTransport implements PushTransport
{
    public function __construct(private ?FirebaseSettings $settings = null)
    {
        $this->settings ??= new FirebaseSettings();
    }

    public function send(string $token, array $payload): array
    {
        $config = $this->settings->publicConfig();
        $projectId = trim($config['project_id']);
        if ($projectId === '') {
            return ['success' => false, 'status_code' => 0, 'error_code' => 'firebase_not_configured'];
        }

        $credentialReference = strtoupper(trim((string) get_option(FirebaseSettings::CREDENTIAL_REFERENCE_OPTION, '')));
        if (! preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $credentialReference)) {
            return ['success' => false, 'status_code' => 0, 'error_code' => 'firebase_auth_unavailable'];
        }

        $accessToken = apply_filters(
            'safecontracts_firebase_access_token',
            '',
            $credentialReference,
            $projectId
        );
        if (! is_string($accessToken) || trim($accessToken) === '') {
            return ['success' => false, 'status_code' => 0, 'error_code' => 'firebase_auth_unavailable'];
        }

        $request = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                ],
                'data' => $this->stringifyData($payload['data'] ?? []),
            ],
        ];
        $response = wp_remote_post(
            'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send',
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . trim($accessToken),
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($request),
            ]
        );
        if (is_wp_error($response)) {
            return ['success' => false, 'status_code' => 0, 'error_code' => 'firebase_transport_error'];
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode >= 200 && $statusCode < 300) {
            return ['success' => true, 'status_code' => $statusCode, 'error_code' => null];
        }
        $body = function_exists('wp_remote_retrieve_body') ? (string) wp_remote_retrieve_body($response) : '';
        return [
            'success' => false,
            'status_code' => $statusCode,
            'error_code' => $this->firebaseErrorCode($statusCode, $body),
        ];
    }

    /** @param array<string, scalar|null> $data @return array<string, string> */
    private function stringifyData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value === null ? '' : (string) $value;
        }
        return $result;
    }

    private function firebaseErrorCode(int $statusCode, string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $details = $decoded['error']['details'] ?? [];
            foreach (is_array($details) ? $details : [] as $detail) {
                if (! is_array($detail)) {
                    continue;
                }
                $fcmCode = strtoupper(trim((string) ($detail['errorCode'] ?? '')));
                $mapped = match ($fcmCode) {
                    'UNREGISTERED' => 'firebase_token_not_found',
                    'SENDER_ID_MISMATCH' => 'firebase_sender_id_mismatch',
                    'INVALID_ARGUMENT' => 'firebase_invalid_argument',
                    'QUOTA_EXCEEDED' => 'firebase_quota_exceeded',
                    'UNAVAILABLE' => 'firebase_unavailable',
                    'INTERNAL' => 'firebase_internal',
                    'THIRD_PARTY_AUTH_ERROR' => 'firebase_third_party_auth_error',
                    default => '',
                };
                if ($mapped !== '') {
                    return $mapped;
                }
            }

            $status = strtoupper(trim((string) ($decoded['error']['status'] ?? '')));
            $mappedStatus = match ($status) {
                'PERMISSION_DENIED' => 'firebase_permission_denied',
                'UNAUTHENTICATED' => 'firebase_auth_failed',
                'INVALID_ARGUMENT' => 'firebase_invalid_argument',
                'RESOURCE_EXHAUSTED' => 'firebase_quota_exceeded',
                'UNAVAILABLE' => 'firebase_unavailable',
                'INTERNAL' => 'firebase_internal',
                'NOT_FOUND' => 'firebase_token_not_found',
                default => '',
            };
            if ($mappedStatus !== '') {
                return $mappedStatus;
            }
        }

        if ($statusCode === 404) {
            return 'firebase_token_not_found';
        }
        if ($statusCode === 401) {
            return 'firebase_auth_failed';
        }
        if ($statusCode === 403) {
            return 'firebase_permission_denied';
        }
        return 'firebase_http_' . max(0, $statusCode);
    }
}
