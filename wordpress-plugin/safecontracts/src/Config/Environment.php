<?php

declare(strict_types=1);

namespace SafeContracts\Config;

final class Environment
{
    public const DEVELOPMENT = 'development';
    public const STAGING = 'staging';
    public const PRODUCTION = 'production';
    public const TESTING = 'testing';

    /** @return list<string> */
    public static function allowed(): array
    {
        return [
            self::DEVELOPMENT,
            self::STAGING,
            self::PRODUCTION,
            self::TESTING,
        ];
    }

    public static function name(): string
    {
        $raw = '';
        if (defined('SAFECONTRACTS_ENV')) {
            $raw = (string) constant('SAFECONTRACTS_ENV');
        } else {
            $value = getenv('SAFECONTRACTS_ENV');
            $raw = $value === false ? '' : (string) $value;
        }

        $normalized = strtolower(trim($raw));
        if (! in_array($normalized, self::allowed(), true)) {
            return self::PRODUCTION;
        }

        return $normalized;
    }

    public static function debugEnabled(): bool
    {
        if (self::name() === self::PRODUCTION) {
            return false;
        }

        $raw = null;
        if (defined('SAFECONTRACTS_DEBUG')) {
            $raw = constant('SAFECONTRACTS_DEBUG');
        } else {
            $value = getenv('SAFECONTRACTS_DEBUG');
            $raw = $value === false ? null : $value;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }
}
