<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class FirebaseServiceAccountVault
{
    public const OPTION = 'safecontracts_firebase_service_account_vault';
    public const REFERENCE = 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT';
    public const MAX_JSON_BYTES = 65536;

    private const VERSION = 1;
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'safecontracts-firebase-service-account-v1';

    /**
     * @return array{project_id:string,client_email:string,key_fingerprint:string,stored_at:string}
     */
    public function storeJson(string $json, string $expectedProjectId): array
    {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Firebase service-account JSON has an invalid size.');
        }

        $credential = $this->validateCredential($json, $expectedProjectId);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $json,
            self::CIPHER,
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD,
            16
        );
        if (! is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Firebase credential encryption is unavailable.');
        }

        $metadata = [
            'project_id' => $credential['project_id'],
            'client_email' => $credential['client_email'],
            'key_fingerprint' => substr(hash('sha256', $credential['private_key_id']), 0, 16),
            'stored_at' => gmdate(DATE_ATOM),
        ];
        $record = [
            'version' => self::VERSION,
            'cipher' => self::CIPHER,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
            'metadata' => $metadata,
        ];
        update_option(self::OPTION, $record, false);
        do_action('safecontracts_firebase_service_account_stored', get_current_user_id(), $metadata['project_id']);
        return $metadata;
    }

    /**
     * @return array{type:string,project_id:string,private_key_id:string,private_key:string,client_email:string,token_uri:string}
     */
    public function credential(string $expectedProjectId = ''): array
    {
        $record = get_option(self::OPTION, null);
        if (! is_array($record)
            || (int) ($record['version'] ?? 0) !== self::VERSION
            || ($record['cipher'] ?? '') !== self::CIPHER) {
            throw new RuntimeException('Firebase service-account credential is not configured.');
        }

        $iv = $this->decodeField($record['iv'] ?? null, 12);
        $tag = $this->decodeField($record['tag'] ?? null, 16);
        $ciphertext = $this->decodeField($record['ciphertext'] ?? null, null);
        if ($ciphertext === '') {
            throw new RuntimeException('Stored Firebase credential is invalid.');
        }

        $json = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD
        );
        if (! is_string($json) || $json === '') {
            throw new RuntimeException('Stored Firebase credential cannot be decrypted. Upload the service account again.');
        }
        return $this->validateCredential($json, $expectedProjectId);
    }

    public function configured(string $expectedProjectId = ''): bool
    {
        try {
            $this->credential($expectedProjectId);
            return true;
        } catch (Throwable $error) {
            unset($error);
            return false;
        }
    }

    /**
     * @return array{project_id:string,client_email:string,key_fingerprint:string,stored_at:string}|null
     */
    public function metadata(): ?array
    {
        $record = get_option(self::OPTION, null);
        $metadata = is_array($record) ? ($record['metadata'] ?? null) : null;
        if (! is_array($metadata)) {
            return null;
        }
        $projectId = trim((string) ($metadata['project_id'] ?? ''));
        $clientEmail = trim((string) ($metadata['client_email'] ?? ''));
        $fingerprint = trim((string) ($metadata['key_fingerprint'] ?? ''));
        $storedAt = trim((string) ($metadata['stored_at'] ?? ''));
        if ($projectId === '' || $clientEmail === '' || $fingerprint === '' || $storedAt === '') {
            return null;
        }
        return [
            'project_id' => $projectId,
            'client_email' => $clientEmail,
            'key_fingerprint' => $fingerprint,
            'stored_at' => $storedAt,
        ];
    }

    public function delete(): void
    {
        delete_option(self::OPTION);
        do_action('safecontracts_firebase_service_account_deleted', get_current_user_id());
    }

    /**
     * @return array{type:string,project_id:string,private_key_id:string,private_key:string,client_email:string,token_uri:string}
     */
    private function validateCredential(string $json, string $expectedProjectId): array
    {
        try {
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            unset($error);
            throw new InvalidArgumentException('Firebase service-account JSON is malformed.');
        }
        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'service_account') {
            throw new InvalidArgumentException('Firebase credential must be a service-account JSON file.');
        }

        $projectId = trim((string) ($decoded['project_id'] ?? ''));
        $privateKeyId = trim((string) ($decoded['private_key_id'] ?? ''));
        $privateKey = (string) ($decoded['private_key'] ?? '');
        $clientEmail = strtolower(trim((string) ($decoded['client_email'] ?? '')));
        $tokenUri = trim((string) ($decoded['token_uri'] ?? ''));
        $expectedProjectId = trim($expectedProjectId);

        if (! preg_match('/^[a-z][a-z0-9-]{3,62}$/', $projectId)) {
            throw new InvalidArgumentException('Firebase service-account project ID is invalid.');
        }
        if ($expectedProjectId !== '' && ! hash_equals($expectedProjectId, $projectId)) {
            throw new InvalidArgumentException('Firebase service-account project does not match SafeContracts Firebase settings.');
        }
        if (! preg_match('/^[A-Za-z0-9_-]{8,160}$/', $privateKeyId)) {
            throw new InvalidArgumentException('Firebase service-account key identifier is invalid.');
        }
        if (! filter_var($clientEmail, FILTER_VALIDATE_EMAIL)
            || ! str_ends_with($clientEmail, '.iam.gserviceaccount.com')) {
            throw new InvalidArgumentException('Firebase service-account client email is invalid.');
        }
        if (! in_array($tokenUri, [
            'https://oauth2.googleapis.com/token',
            'https://accounts.google.com/o/oauth2/token',
        ], true)) {
            throw new InvalidArgumentException('Firebase service-account token endpoint is not allowed.');
        }
        if (strlen($privateKey) < 512
            || strlen($privateKey) > 16384
            || ! str_contains($privateKey, '-----BEGIN PRIVATE KEY-----')
            || ! str_contains($privateKey, '-----END PRIVATE KEY-----')) {
            throw new InvalidArgumentException('Firebase service-account private key is invalid.');
        }
        $key = @openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new InvalidArgumentException('Firebase service-account private key cannot be loaded.');
        }

        return [
            'type' => 'service_account',
            'project_id' => $projectId,
            'private_key_id' => $privateKeyId,
            'private_key' => $privateKey,
            'client_email' => $clientEmail,
            'token_uri' => $tokenUri,
        ];
    }

    private function encryptionKey(): string
    {
        if (! function_exists('openssl_encrypt') || ! function_exists('openssl_decrypt')) {
            throw new RuntimeException('OpenSSL is required for encrypted Firebase credential storage.');
        }
        $material = wp_salt('auth') . "\0" . wp_salt('secure_auth');
        if (strlen($material) < 32) {
            throw new RuntimeException('WordPress security salts are required for encrypted Firebase credential storage.');
        }
        return hash_hkdf('sha256', $material, 32, 'SafeContracts Firebase credential vault v1');
    }

    private function decodeField(mixed $value, ?int $expectedLength): string
    {
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Stored Firebase credential is invalid.');
        }
        $decoded = base64_decode($value, true);
        if (! is_string($decoded) || ($expectedLength !== null && strlen($decoded) !== $expectedLength)) {
            throw new RuntimeException('Stored Firebase credential is invalid.');
        }
        return $decoded;
    }
}
