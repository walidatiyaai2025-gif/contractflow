<?php

declare(strict_types=1);

namespace SafeContracts\Settings;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;

final class GeneralSettings
{
    public const OPTION = 'safecontracts_general_settings';

    /** @return array{organization_name:string,currency_code:string,currency_symbol:string,admin_page_size:int} */
    public function read(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        $defaults = self::defaults();

        return [
            'organization_name' => $this->readText($stored['organization_name'] ?? $defaults['organization_name'], $defaults['organization_name'], 191),
            'currency_code' => $this->readCurrency($stored['currency_code'] ?? $defaults['currency_code']),
            'currency_symbol' => $this->readOptionalText($stored['currency_symbol'] ?? $defaults['currency_symbol'], 16),
            'admin_page_size' => $this->readPageSize($stored['admin_page_size'] ?? $defaults['admin_page_size']),
        ];
    }

    /** @return array{organization_name:string,currency_code:string,currency_symbol:string,admin_page_size:int} */
    public function save(array $input): array
    {
        $this->requireManage();
        $settings = [
            'organization_name' => $this->normalizeText($input['organization_name'] ?? '', 'Organization name', 191),
            'currency_code' => $this->normalizeCurrency($input['currency_code'] ?? ''),
            'currency_symbol' => $this->normalizeOptionalText($input['currency_symbol'] ?? '', 'Currency symbol', 16),
            'admin_page_size' => $this->normalizePageSize($input['admin_page_size'] ?? 50),
        ];

        update_option(self::OPTION, $settings, false);
        do_action('safecontracts_general_settings_saved', get_current_user_id(), $settings);
        return $settings;
    }

    /** @return array{organization_name:string,currency_code:string,currency_symbol:string,admin_page_size:int} */
    public static function defaults(): array
    {
        return [
            'organization_name' => 'SafeContracts',
            'currency_code' => '',
            'currency_symbol' => '',
            'admin_page_size' => 50,
        ];
    }

    private function normalizeText(mixed $value, string $field, int $maxLength): string
    {
        $text = trim(strip_tags(Input::string($value, $field)));
        if ($text === '' || strlen($text) > $maxLength || preg_match('/[\r\n\x00]/', $text)) {
            throw new InvalidArgumentException("{$field} is invalid.");
        }
        return $text;
    }

    private function normalizeOptionalText(mixed $value, string $field, int $maxLength): string
    {
        $text = trim(strip_tags(Input::string($value, $field)));
        if (strlen($text) > $maxLength || preg_match('/[\r\n\x00]/', $text)) {
            throw new InvalidArgumentException("{$field} is invalid.");
        }
        return $text;
    }

    private function normalizeCurrency(mixed $value): string
    {
        $currency = strtoupper(trim(Input::string($value, 'Currency code')));
        if ($currency !== '' && ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency code must be a three-letter ISO-style code or blank until configured.');
        }
        return $currency;
    }

    private function normalizePageSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        if ($size === false || $size < 10 || $size > 200) {
            throw new InvalidArgumentException('Admin page size must be between 10 and 200.');
        }
        return (int) $size;
    }

    private function readText(mixed $value, string $fallback, int $maxLength): string
    {
        if (! is_scalar($value) && $value !== null) {
            return $fallback;
        }
        $text = trim(strip_tags((string) $value));
        return $text !== '' && strlen($text) <= $maxLength ? $text : $fallback;
    }

    private function readOptionalText(mixed $value, int $maxLength): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }
        $text = trim(strip_tags((string) $value));
        return strlen($text) <= $maxLength && ! preg_match('/[\r\n\x00]/', $text) ? $text : '';
    }

    private function readCurrency(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }
        $currency = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : '';
    }

    private function readPageSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        return $size !== false && $size >= 10 && $size <= 200 ? (int) $size : self::defaults()['admin_page_size'];
    }

    private function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            throw new DomainException('You do not have permission to manage SafeContracts system settings.');
        }
    }
}
