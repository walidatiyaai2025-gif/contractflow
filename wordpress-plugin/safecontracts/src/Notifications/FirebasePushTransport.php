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

        $accessToken = apply_filters('safecontracts_firebase_access_token', '', $credentialReference, $projectId);
        if (! is_string($accessToken) || trim($accessToken) === '') {
            return ['success' => false, 'status_code' => 0, 'error_code' => 'firebase_auth_unavailable'];
        }

        $request = $this->buildRequest($token, $payload);
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

    /** @param array<string,mixed> $payload @return array{message:array<string,mixed>} */
    private function buildRequest(string $token, array $payload): array
    {
        $iconKey = sanitize_key((string) ($payload['icon_key'] ?? 'safe_contracts'));
        $soundSettings = new NotificationSoundSettings();
        $configuredSounds = $soundSettings->get();
        $sound = $soundSettings->resolve($payload);
        $message = [
            'token' => $token,
            'notification' => [
                'title' => (string) ($payload['title'] ?? ''),
                'body' => (string) ($payload['body'] ?? ''),
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => $sound['channel_id'],
                    'sound' => $sound['android_sound'],
                    'notification_priority' => 'PRIORITY_HIGH',
                    'visibility' => 'PUBLIC',
                    'tag' => $iconKey !== '' ? 'safe_contracts_' . $iconKey : 'safe_contracts_alert',
                ],
            ],
        ];

        $rawData = $payload['data'] ?? [];
        $data = $this->stringifyData(is_array($rawData) ? $rawData : []);
        if ($data !== [] || ($configuredSounds['enabled'] ?? false)) {
            $data['sound_key'] = $sound['sound_key'];
            $data['notification_category'] = $sound['category'];
            $data['channel_id'] = $sound['channel_id'];
            $message['data'] = $data;
        }

        return ['message' => $message];
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
            $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            $details = is_array($error['details'] ?? null) ? $error['details'] : [];
            $invalidRegistrationToken = $this->isInvalidRegistrationToken((string) ($error['message'] ?? ''), $details);

            foreach ($details as $detail) {
                if (! is_array($detail)) {
                    continue;
                }
                $fcmCode = strtoupper(trim((string) ($detail['errorCode'] ?? '')));
                $mapped = match ($fcmCode) {
                    'UNREGISTERED' => 'firebase_token_not_found',
                    'SENDER_ID_MISMATCH' => 'firebase_sender_id_mismatch',
                    'INVALID_ARGUMENT' => $invalidRegistrationToken ? 'firebase_token_not_found' : 'firebase_invalid_argument',
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

            $status = strtoupper(trim((string) ($error['status'] ?? '')));
            $mappedStatus = match ($status) {
                'PERMISSION_DENIED' => 'firebase_permission_denied',
                'UNAUTHENTICATED' => 'firebase_auth_failed',
                'INVALID_ARGUMENT' => $invalidRegistrationToken ? 'firebase_token_not_found' : 'firebase_invalid_argument',
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

    /** @param list<mixed> $details */
    private function isInvalidRegistrationToken(string $message, array $details): bool
    {
        $message = strtolower(trim($message));
        if (str_contains($message, 'registration token') && (str_contains($message, 'invalid') || str_contains($message, 'not a valid fcm'))) {
            return true;
        }

        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $violations = $detail['fieldViolations'] ?? [];
            foreach (is_array($violations) ? $violations : [] as $violation) {
                if (! is_array($violation)) {
                    continue;
                }
                $field = strtolower(trim((string) ($violation['field'] ?? '')));
                $description = strtolower(trim((string) ($violation['description'] ?? '')));
                if ($field === 'message.token' || str_contains($description, 'registration token')) {
                    return true;
                }
            }
        }
        return false;
    }
}
