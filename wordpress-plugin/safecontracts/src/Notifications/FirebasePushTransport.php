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

        $credentialReference = (string) get_option(FirebaseSettings::CREDENTIAL_REFERENCE_OPTION, '');
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
        return [
            'success' => false,
            'status_code' => $statusCode,
            'error_code' => $statusCode === 404 ? 'firebase_token_not_found' : 'firebase_http_' . $statusCode,
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
}
