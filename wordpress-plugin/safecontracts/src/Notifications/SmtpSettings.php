<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use RuntimeException;

final class SmtpSettings
{
    public const HOST_OPTION = 'safecontracts_notification_smtp_host';
    public const PORT_OPTION = 'safecontracts_notification_smtp_port';
    public const ENCRYPTION_OPTION = 'safecontracts_notification_smtp_encryption';
    public const USERNAME_OPTION = 'safecontracts_notification_smtp_username';
    public const PASSWORD_OPTION = 'safecontracts_notification_smtp_password';
    public const TIMEOUT_OPTION = 'safecontracts_notification_smtp_timeout';

    /** @return array{host:string,port:int,encryption:string,username:string,password:string,password_configured:bool,timeout:int} */
    public function get(): array
    {
        $encrypted = trim((string) get_option(self::PASSWORD_OPTION, ''));
        $password = $encrypted !== '' ? $this->decryptSecret($encrypted) : '';
        return [
            'host' => trim((string) get_option(self::HOST_OPTION, '')),
            'port' => $this->normalizePort(get_option(self::PORT_OPTION, 587)),
            'encryption' => $this->normalizeEncryption((string) get_option(self::ENCRYPTION_OPTION, 'tls')),
            'username' => trim((string) get_option(self::USERNAME_OPTION, '')),
            'password' => $password,
            'password_configured' => $password !== '',
            'timeout' => $this->normalizeTimeout(get_option(self::TIMEOUT_OPTION, 15)),
        ];
    }

    /** @return array{host:string,port:int,encryption:string,username:string,password_configured:bool,timeout:int} */
    public function save(array $input): array
    {
        $host = strtolower(trim(sanitize_text_field((string) ($input['host'] ?? ''))));
        if (! $this->validHost($host)) {
            throw new InvalidArgumentException('SMTP host is required and must be a valid hostname or IP address.');
        }
        $port = $this->normalizePort($input['port'] ?? 587);
        $encryption = $this->normalizeEncryption((string) ($input['encryption'] ?? 'tls'));
        $username = trim(sanitize_text_field((string) ($input['username'] ?? '')));
        if (strlen($username) > 191 || str_contains($username, "\r") || str_contains($username, "\n")) {
            throw new InvalidArgumentException('SMTP username is invalid.');
        }
        $timeout = $this->normalizeTimeout($input['timeout'] ?? 15);

        $current = $this->get();
        $passwordInput = (string) ($input['password'] ?? '');
        $clearPassword = NotificationRule::normalizeBool($input['clear_password'] ?? false);
        $password = $clearPassword ? '' : ($passwordInput !== '' ? $passwordInput : $current['password']);
        if (strlen($password) > 1024 || str_contains($password, "\r") || str_contains($password, "\n")) {
            throw new InvalidArgumentException('SMTP password is invalid.');
        }
        if (($username === '') !== ($password === '')) {
            throw new InvalidArgumentException('SMTP username and password must either both be configured or both be empty.');
        }

        update_option(self::HOST_OPTION, $host, false);
        update_option(self::PORT_OPTION, (string) $port, false);
        update_option(self::ENCRYPTION_OPTION, $encryption, false);
        update_option(self::USERNAME_OPTION, $username, false);
        update_option(self::TIMEOUT_OPTION, (string) $timeout, false);
        update_option(self::PASSWORD_OPTION, $password === '' ? '' : $this->encryptSecret($password), false);

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password_configured' => $password !== '',
            'timeout' => $timeout,
        ];
    }

    private function normalizePort(mixed $value): int
    {
        $port = (int) $value;
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('SMTP port must be between 1 and 65535.');
        }
        return $port;
    }

    private function normalizeTimeout(mixed $value): int
    {
        $timeout = (int) $value;
        if ($timeout < 3 || $timeout > 60) {
            throw new InvalidArgumentException('SMTP timeout must be between 3 and 60 seconds.');
        }
        return $timeout;
    }

    private function normalizeEncryption(string $value): string
    {
        $value = strtolower(trim($value));
        if (! in_array($value, ['tls', 'ssl', 'none'], true)) {
            throw new InvalidArgumentException('SMTP encryption must be tls, ssl or none.');
        }
        return $value;
    }

    private function validHost(string $host): bool
    {
        if ($host === '' || strlen($host) > 253 || str_contains($host, "\r") || str_contains($host, "\n")) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }
        return (bool) preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host);
    }

    private function encryptSecret(string $plain): string
    {
        if (! function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL is required to store the SMTP password securely.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $this->secretKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if (! is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to encrypt SMTP password.');
        }
        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    private function decryptSecret(string $stored): string
    {
        if (! str_starts_with($stored, 'v1:') || ! function_exists('openssl_decrypt')) {
            return '';
        }
        $decoded = base64_decode(substr($stored, 3), true);
        if (! is_string($decoded) || strlen($decoded) < 29) {
            return '';
        }
        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->secretKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plain) ? $plain : '';
    }

    private function secretKey(): string
    {
        $material = function_exists('wp_salt') ? (string) wp_salt('auth') : '';
        if ($material === '' && defined('AUTH_KEY')) {
            $material = (string) constant('AUTH_KEY');
        }
        if ($material === '') {
            throw new RuntimeException('WordPress AUTH_KEY/wp_salt is required to protect SMTP credentials.');
        }
        return hash('sha256', $material . '|safecontracts-direct-smtp-v1', true);
    }
}
