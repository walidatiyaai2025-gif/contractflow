<?php

declare(strict_types=1);

namespace SafeContracts\Settings;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;

final class MobileConfiguration
{
    public const OPTION = 'safecontracts_mobile_configuration';

    /** @return array{support_text:string,default_page_size:int,excel_export_enabled:bool,push_notifications_enabled:bool,collection_entry_enabled:bool} */
    public function read(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        $defaults = self::defaults();

        return [
            'support_text' => $this->readText($stored['support_text'] ?? $defaults['support_text']),
            'default_page_size' => $this->readPageSize($stored['default_page_size'] ?? $defaults['default_page_size']),
            'excel_export_enabled' => $this->readBool($stored['excel_export_enabled'] ?? $defaults['excel_export_enabled']),
            'push_notifications_enabled' => $this->readBool($stored['push_notifications_enabled'] ?? $defaults['push_notifications_enabled']),
            'collection_entry_enabled' => $this->readBool($stored['collection_entry_enabled'] ?? $defaults['collection_entry_enabled']),
        ];
    }

    /** @return array{support_text:string,default_page_size:int,excel_export_enabled:bool,push_notifications_enabled:bool,collection_entry_enabled:bool} */
    public function save(array $input): array
    {
        $this->requireManage();
        $config = [
            'support_text' => $this->normalizeText($input['support_text'] ?? ''),
            'default_page_size' => $this->normalizePageSize($input['default_page_size'] ?? 25),
            'excel_export_enabled' => $this->normalizeBool($input['excel_export_enabled'] ?? false),
            'push_notifications_enabled' => $this->normalizeBool($input['push_notifications_enabled'] ?? false),
            'collection_entry_enabled' => $this->normalizeBool($input['collection_entry_enabled'] ?? false),
        ];

        update_option(self::OPTION, $config, false);
        do_action('safecontracts_mobile_configuration_saved', get_current_user_id(), $config);
        return $config;
    }

    /** @return array{support_text:string,default_page_size:int,excel_export_enabled:bool,push_notifications_enabled:bool,collection_entry_enabled:bool} */
    public static function defaults(): array
    {
        return [
            'support_text' => '',
            'default_page_size' => 25,
            'excel_export_enabled' => false,
            'push_notifications_enabled' => false,
            'collection_entry_enabled' => false,
        ];
    }

    private function normalizeText(mixed $value): string
    {
        $text = trim(strip_tags(Input::string($value, 'Mobile support text')));
        if (strlen($text) > 500 || preg_match('/[\x00]/', $text)) {
            throw new InvalidArgumentException('Mobile support text must not exceed 500 characters.');
        }
        return $text;
    }

    private function normalizePageSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        if ($size === false || $size < 10 || $size > 200) {
            throw new InvalidArgumentException('Mobile default page size must be between 10 and 200.');
        }
        return (int) $size;
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true' || $value === 'on') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false' || $value === null || $value === '') {
            return false;
        }
        throw new InvalidArgumentException('Mobile feature flag value is invalid.');
    }

    private function readText(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }
        $text = trim(strip_tags((string) $value));
        return strlen($text) <= 500 ? $text : '';
    }

    private function readPageSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        return $size !== false && $size >= 10 && $size <= 200 ? (int) $size : self::defaults()['default_page_size'];
    }

    private function readBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
    }

    private function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            throw new DomainException('You do not have permission to manage SafeContracts mobile configuration.');
        }
    }
}
