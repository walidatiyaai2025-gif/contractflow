<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class FirebaseSettingsService
{
    public const SERVER_OPTION = 'safecontracts_firebase_service_account';
    public const CLIENT_OPTION = 'safecontracts_firebase_client_config';

    /** @var list<string> */
    private const CLIENT_KEYS = [
        'apiKey',
        'authDomain',
        'projectId',
        'storageBucket',
        'messagingSenderId',
        'appId',
        'measurementId',
    ];

    /** @return array<string, string> */
    public function saveServiceAccount(mixed $value): array
    {
        $this->requireManageFirebase();
        $account = $this->normalizeServiceAccount($value);
        update_option(self::SERVER_OPTION, $account, false);
        do_action('safecontracts_firebase_server_settings_saved', get_current_user_id());
        return $this->serverSummary($account);
    }

    /** @return array<string, string> */
    public function saveClientConfig(array $input): array
    {
        $this->requireManageFirebase();
        $config = [];
        foreach (self::CLIENT_KEYS as $key) {
            if (! array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
                continue;
            }
            $value = trim((string) $input[$key]);
            if ($value === '' || strlen($value) > 255) {
                throw new InvalidArgumentException('Firebase client setting ' . $key . ' is invalid.');
            }
            $config[$key] = $value;
        }
        foreach (['apiKey', 'projectId', 'messagingSenderId', 'appId'] as $required) {
            if (! isset($config[$required])) {
                throw new InvalidArgumentException('Firebase client setting ' . $required . ' is required.');
            }
        }
        if (! preg_match('/^[a-z0-9-]{4,100}$/', $config['projectId'])) {
            throw new InvalidArgumentException('Firebase client projectId is invalid.');
        }
        update_option(self::CLIENT_OPTION, $config, false);
        do_action('safecontracts_firebase_client_settings_saved', get_current_user_id());
        return $config;
    }

    /**
     * Backend-only credential accessor used by the push transport.
     * Never return this from REST/bootstrap responses.
     *
     * @return array<string, string>
     */
    public function serverCredentialsForDelivery(): array
    {
        $stored = get_option(self::SERVER_OPTION, []);
        if (! is_array($stored) || $stored === []) {
            throw new DomainException('Firebase server credentials are not configured.');
        }
        return $this->normalizeServiceAccount($stored);
    }

    /** @return array<string, string> */
    public function clientConfig(): array
    {
        $stored = get_option(self::CLIENT_OPTION, []);
        if (! is_array($stored)) {
            return [];
        }
        $safe = [];
        foreach (self::CLIENT_KEYS as $key) {
            if (isset($stored[$key]) && is_scalar($stored[$key])) {
                $safe[$key] = (string) $stored[$key];
            }
        }
        return $safe;
    }

    /** @return array<string, string> */
    private function normalizeServiceAccount(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Firebase service account must be valid JSON.');
            }
            $value = $decoded;
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException('Firebase service account must be an object or JSON object.');
        }

        $required = ['project_id', 'private_key', 'client_email'];
        $account = [];
        foreach ($required as $key) {
            $text = trim((string) ($value[$key] ?? ''));
            if ($text === '' || strlen($text) > 10000) {
                throw new InvalidArgumentException('Firebase service-account field ' . $key . ' is required.');
            }
            $account[$key] = $text;
        }
        if (! preg_match('/^[a-z0-9-]{4,100}$/', $account['project_id'])) {
            throw new InvalidArgumentException('Firebase service-account project_id is invalid.');
        }
        if (! filter_var($account['client_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Firebase service-account client_email is invalid.');
        }
        if (! str_contains($account['private_key'], 'BEGIN PRIVATE KEY') || ! str_contains($account['private_key'], 'END PRIVATE KEY')) {
            throw new InvalidArgumentException('Firebase service-account private_key is not a PEM private key.');
        }

        $tokenUri = trim((string) ($value['token_uri'] ?? 'https://oauth2.googleapis.com/token'));
        if (! filter_var($tokenUri, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($tokenUri), 'https://')) {
            throw new InvalidArgumentException('Firebase service-account token_uri must be HTTPS.');
        }
        $account['token_uri'] = $tokenUri;
        return $account;
    }

    /** @param array<string, string> $account @return array<string, string> */
    private function serverSummary(array $account): array
    {
        return [
            'project_id' => $account['project_id'],
            'client_email' => $account['client_email'],
            'token_uri' => $account['token_uri'],
        ];
    }

    private function requireManageFirebase(): void
    {
        if (! current_user_can(Capabilities::MANAGE_FIREBASE)) {
            throw new DomainException('You do not have permission to manage SafeContracts Firebase settings.');
        }
    }
}
