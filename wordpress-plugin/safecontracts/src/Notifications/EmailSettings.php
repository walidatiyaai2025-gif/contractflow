<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use SafeContracts\Tenancy\NonCoreTenantScope;

final class EmailSettings
{
    public const ENABLED_OPTION = 'safecontracts_notification_email_enabled';
    public const FROM_NAME_OPTION = 'safecontracts_notification_email_from_name';
    public const FROM_ADDRESS_OPTION = 'safecontracts_notification_email_from_address';

    /** @return array{enabled:bool,from_name:string,from_address:string} */
    public function get(): array
    {
        $siteName = function_exists('get_bloginfo') ? trim((string) get_bloginfo('name')) : 'Safe Contracts';
        $adminEmail = trim((string) get_option('admin_email', ''));
        $tenantId = NonCoreTenantScope::tenantId();
        $suffix = $tenantId === null ? '' : '_tenant_' . $tenantId;
        return [
            'enabled' => (bool) get_option(self::ENABLED_OPTION . $suffix, false),
            'from_name' => trim((string) get_option(self::FROM_NAME_OPTION . $suffix, $siteName !== '' ? $siteName : 'Safe Contracts')),
            'from_address' => trim((string) get_option(self::FROM_ADDRESS_OPTION . $suffix, $adminEmail)),
        ];
    }

    /** @return array{enabled:bool,from_name:string,from_address:string} */
    public function save(array $input): array
    {
        $enabled = NotificationRule::normalizeBool($input['enabled'] ?? false);
        $fromName = trim(sanitize_text_field((string) ($input['from_name'] ?? '')));
        $fromAddress = function_exists('sanitize_email') ? sanitize_email((string) ($input['from_address'] ?? '')) : trim((string) ($input['from_address'] ?? ''));
        if ($fromName === '' || strlen($fromName) > 191) {
            throw new InvalidArgumentException('Notification email sender name is required and must not exceed 191 characters.');
        }
        if (! self::validEmail($fromAddress)) {
            throw new InvalidArgumentException('Notification email sender address is invalid.');
        }

        $tenantId = NonCoreTenantScope::tenantId();
        $suffix = $tenantId === null ? '' : '_tenant_' . $tenantId;
        update_option(self::ENABLED_OPTION . $suffix, $enabled ? '1' : '0', false);
        update_option(self::FROM_NAME_OPTION . $suffix, $fromName, false);
        update_option(self::FROM_ADDRESS_OPTION . $suffix, $fromAddress, false);
        return ['enabled' => $enabled, 'from_name' => $fromName, 'from_address' => $fromAddress];
    }

    public static function validEmail(string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            return false;
        }
        if (function_exists('is_email')) {
            return (bool) is_email($email);
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
