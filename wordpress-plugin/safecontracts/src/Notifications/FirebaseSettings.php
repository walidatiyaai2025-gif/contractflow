<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;

final class FirebaseSettings
{
    public const PUBLIC_OPTION = 'safecontracts_firebase_public_config';
    public const CREDENTIAL_REFERENCE_OPTION = 'safecontracts_firebase_credential_reference';

    /** @return array{project_id:string,messaging_sender_id:string,app_id:string} */
    public function publicConfig(): array
    {
        $stored = get_option(self::PUBLIC_OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        return [
            'project_id' => (string) ($stored['project_id'] ?? ''),
            'messaging_sender_id' => (string) ($stored['messaging_sender_id'] ?? ''),
            'app_id' => (string) ($stored['app_id'] ?? ''),
        ];
    }

    /** @return array{project_id:string,messaging_sender_id:string,app_id:string} */
    public function savePublic(array $input): array
    {
        $this->requireManage();
        $config = [
            'project_id' => $this->normalizeValue($input['project_id'] ?? '', 'Firebase project ID', 191),
            'messaging_sender_id' => $this->normalizeDigits($input['messaging_sender_id'] ?? ''),
            'app_id' => $this->normalizeValue($input['app_id'] ?? '', 'Firebase app ID', 191),
        ];
        update_option(self::PUBLIC_OPTION, $config, false);
        do_action('safecontracts_firebase_public_settings_saved', get_current_user_id());
        return $config;
    }

    public function saveCredentialReference(mixed $value): string
    {
        $this->requireManage();
        $reference = strtoupper(trim(Input::string($value, 'Firebase credential reference')));
        if (! preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $reference)) {
            throw new InvalidArgumentException('Firebase credential reference must be an environment/secret identifier, not secret content.');
        }
        update_option(self::CREDENTIAL_REFERENCE_OPTION, $reference, false);
        do_action('safecontracts_firebase_credential_reference_saved', get_current_user_id());
        return $reference;
    }

    public function credentialReference(): string
    {
        $this->requireManage();
        return (string) get_option(self::CREDENTIAL_REFERENCE_OPTION, '');
    }

    /** @return array{project_id:string,messaging_sender_id:string,app_id:string,configured:bool} */
    public function safeSummary(): array
    {
        $public = $this->publicConfig();
        return $public + [
            'configured' => trim((string) get_option(self::CREDENTIAL_REFERENCE_OPTION, '')) !== '',
        ];
    }

    private function normalizeValue(mixed $value, string $field, int $max): string
    {
        $text = trim(Input::string($value, $field));
        if ($text === '' || strlen($text) > $max || preg_match('/[\r\n\x00]/', $text)) {
            throw new InvalidArgumentException("{$field} is invalid.");
        }
        return $text;
    }

    private function normalizeDigits(mixed $value): string
    {
        $text = trim(Input::string($value, 'Firebase messaging sender ID'));
        if (! preg_match('/^\d{3,32}$/', $text)) {
            throw new InvalidArgumentException('Firebase messaging sender ID must contain digits only.');
        }
        return $text;
    }

    private function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            throw new DomainException('You do not have permission to manage SafeContracts Firebase settings.');
        }
    }
}
