<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;

final class EmailSettings
{
    public const ENABLED_OPTION = 'safecontracts_notification_email_enabled';
    public const FROM_NAME_OPTION = 'safecontracts_notification_email_from_name';
    public const FROM_ADDRESS_OPTION = 'safecontracts_notification_email_from_address';

    /** @return array{enabled:bool,from_name:string,from_address:string} */
    public function get(): array
    {
        $siteName = trim((string) get_bloginfo('name'));
        $adminEmail = trim((string) get_option('admin_email', ''));
        return [
            'enabled' => (bool) get_option(self::ENABLED_OPTION, false),
            'from_name' => trim((string) get_option(self::FROM_NAME_OPTION, $siteName !== '' ? $siteName : 'Safe Contracts')),
            'from_address' => trim((string) get_option(self::FROM_ADDRESS_OPTION, $adminEmail)),
        ];
    }

    /** @return array{enabled:bool,from_name:string,from_address:string} */
    public function save(array $input): array
    {
        $enabled = NotificationRule::normalizeBool($input['enabled'] ?? false);
        $fromName = trim(sanitize_text_field((string) ($input['from_name'] ?? '')));
        $fromAddress = sanitize_email((string) ($input['from_address'] ?? ''));
        if ($fromName === '' || strlen($fromName) > 191) {
            throw new InvalidArgumentException('Notification email sender name is required and must not exceed 191 characters.');
        }
        if ($fromAddress === '' || ! is_email($fromAddress)) {
            throw new InvalidArgumentException('Notification email sender address is invalid.');
        }
        update_option(self::ENABLED_OPTION, $enabled ? '1' : '0', false);
        update_option(self::FROM_NAME_OPTION, $fromName, false);
        update_option(self::FROM_ADDRESS_OPTION, $fromAddress, false);
        return ['enabled' => $enabled, 'from_name' => $fromName, 'from_address' => $fromAddress];
    }
}
